import fs , { rmSync, readdir, existsSync } from 'fs'
import path , { join } from 'path'
import https from "https"
import pino from 'pino'

import makeWASocketModule, {
    useMultiFileAuthState,
    makeCacheableSignalKeyStore,
    DisconnectReason,
    delay,
    downloadMediaMessage,
    extractMessageContent,
    getAggregateVotesInPollMessage,
    getContentType,
    normalizeMessageContent,
    fetchLatestBaileysVersion,
    WAMessageStatus,
} from 'baileys'

import proto from 'baileys'

import makeInMemoryStore from './store/memory-store.js'

import { toDataURL } from 'qrcode'
import __dirname from './dirname.js'
import response from './response.js'
import { downloadImage } from './utils/download.js'
import axios from 'axios'
import NodeCache from 'node-cache'

const msgRetryCounterCache = new NodeCache()

const sessions = new Map()
const retries = new Map()

const APP_WEBHOOK_ALLOWED_EVENTS = ['CONNECTION_UPDATE','MESSAGES_UPSERT']
const webhookUrl = process.env.APP_WEBHOOK_URL

/**
 * آدرس وب‌هوک سبک همگام‌سازی گروه (CRM) — بدون بدنهٔ پیام؛ برای کاهش ترافیک نسبت به وب‌هوک اصلی.
 * APP_GROUP_WEBHOOK_URL اختیاری؛ در غیر این صورت از پایهٔ APP_WEBHOOK_URL (بدون مسیر send-webhook) + /wpwebhook-group.
 */
function buildCrmGroupWebhookUrl() {
    const explicit = process.env.APP_GROUP_WEBHOOK_URL
    if (explicit) {
        return explicit.replace(/\/$/, '')
    }
    const base = process.env.APP_WEBHOOK_URL || ''
    if (!base) {
        return ''
    }
    const cleaned = base.replace(/\/send-webhook.*$/i, '').replace(/\/$/, '')
    return `${cleaned}/wpwebhook-group`
}

/**
 * محتوای قابل‌نمایش پیام پس از باز کردن لایه‌های ephemeral / viewOnce / …
 * مطابق منطق Baileys (extractMessageContent + normalizeMessageContent).
 * @see https://baileys.wiki/docs/api/functions/extractMessageContent
 * @see https://baileys.wiki/docs/api/functions/normalizeMessageContent
 */
function resolveMessageContent(protoMsg) {
    if (!protoMsg) return null
    let c = extractMessageContent(protoMsg)
    if (c) return c
    c = normalizeMessageContent(protoMsg)
    if (c) {
        const again = extractMessageContent(c)
        if (again) return again
        return c
    }
    // اگر نسخهٔ baileys هنوز wrapper جدیدی را در normalize ندارد (مثلاً botInvokeMessage)
    let cur = protoMsg
    for (let i = 0; i < 8; i++) {
        const inner =
            cur?.ephemeralMessage?.message ||
            cur?.viewOnceMessage?.message ||
            cur?.viewOnceMessageV2?.message ||
            cur?.viewOnceMessageV2Extension?.message ||
            cur?.documentWithCaptionMessage?.message ||
            cur?.editedMessage?.message ||
            cur?.associatedChildMessage?.message ||
            cur?.groupStatusMessage?.message ||
            cur?.groupStatusMessageV2?.message ||
            cur?.botInvokeMessage?.message ||
            cur?.pinInChatMessage?.message ||
            cur?.commentMessage?.message
        if (!inner) break
        cur = inner
        const ex = extractMessageContent(cur)
        if (ex) return ex
        const norm = normalizeMessageContent(cur)
        if (norm) {
            const ex2 = extractMessageContent(norm)
            if (ex2) return ex2
            cur = norm
        }
    }
    return extractMessageContent(cur) || null
}

/**
 * شمارهٔ فرستنده برای وب‌هوک: در گروه participant، در چت خصوصی remoteJidAlt یا remoteJid (شامل LID).
 */
function formatWebhookSender(msg) {
    const remote = msg.key?.remoteJid || ''
    const isGroup = remote.endsWith('@g.us')
    let jid = ''
    if (isGroup && msg.key?.participant) {
        jid = msg.key.participant
    } else {
        jid = msg.key?.remoteJidAlt || msg.key?.remoteJid || ''
    }
    return jid.replace(/@s\.whatsapp\.net$/i, '').replace(/@lid$/i, '').replace(/@g\.us$/i, '')
}

const sessionsDir = (sessionId = '') => {
    return join(__dirname, 'sessions', sessionId ? sessionId : '')
}

const isSessionExists = (sessionId) => {
    return sessions.has(sessionId)
}

const isSessionConnected = (sessionId) => {
    return sessions.get(sessionId)?.ws?.socket?.readyState === 1
}

const shouldReconnect = (sessionId) => {
    const maxRetries = parseInt(process.env.WA_SERVER_MAX_RETRIES ?? 0)
    let attempts = retries.get(sessionId) ?? 0

   
    if (attempts < maxRetries || maxRetries === -1) {
        ++attempts

        console.log('Reconnecting...', { attempts, sessionId })
        retries.set(sessionId, attempts)

        return true
    }

    return false
}



async function createSession(sessionId, res = null, options = { usePairingCode: false, phoneNumber: '' }) {
    const sessionFile = 'md_' + sessionId

    const logger = pino({ level: 'silent' })
    const store = makeInMemoryStore({
        preserveDataDuringSync: true,
        backupBeforeSync: false,
        incrementalSave: true,
        maxMessagesPerChat: 150,
        autoSaveInterval: 10000,
        storeFile: sessionsDir(`${sessionId}_store.json`)
    });

    const { state, saveCreds } = await useMultiFileAuthState(sessionsDir(sessionFile))

    // Fetch latest version of WA Web
    const { version, isLatest } = await fetchLatestBaileysVersion()
    console.log(`using WA v${version.join('.')}, isLatest: ${isLatest}`)

    // Load store
    store?.readFromFile(sessionsDir(`${sessionId}_store.json`))

    // Make both Node and Bun compatible
    const makeWASocket = makeWASocketModule.default ?? makeWASocketModule;

    /**
     * @type {import('baileys').AnyWASocket}
     */
    const wa = makeWASocket({
        version,
        printQRInTerminal: false,
        mobile: false,
        auth: {
            creds: state.creds,
            keys: makeCacheableSignalKeyStore(state.keys, logger),
        },
        logger,
        msgRetryCounterCache,
        generateHighQualityLinkPreview: true,
        getMessage,
    })
    store?.bind(wa.ev)

    sessions.set(sessionId, { ...wa, store })

    if (options.usePairingCode && !wa.authState.creds.registered) {
        if (!wa.authState.creds.account) {
            await wa.waitForConnectionUpdate((update) => {
                return Boolean(update.qr)
            })
            const code = await wa.requestPairingCode(options.phoneNumber)
            if (res && !res.headersSent && code !== undefined) {
                response(res, 200, true, 'Verify on your phone and enter the provided code.', { code })
            } else {
                response(res, 500, false, 'Unable to create session.')
            }
        }
    }

    wa.ev.on('creds.update', saveCreds)

    // notify = پیام تازه | append = افزوده به تاریخچه (گاهی سرور همان را برای برخی تحویل‌ها می‌فرستد)
    // @see https://baileys.wiki/docs/api/type-aliases/MessageUpsertType
    const upsertTypes = (process.env.WA_WEBHOOK_UPSERT_TYPES || 'notify,append')
        .split(',')
        .map((s) => s.trim().toLowerCase())
        .filter(Boolean)

    wa.ev.on('messages.upsert', async (m) => {
        const t = String(m.type || '').toLowerCase()
        if (!upsertTypes.includes(t)) return

        const messages = m.messages

        for (const msg of messages) {
            if (!msg.message) continue
            if (msg.key.fromMe) continue

            const remoteJid = msg.key?.remoteJid || ''
            if (remoteJid.endsWith('@g.us')) {
                const groupSyncUrl = buildCrmGroupWebhookUrl()
                if (groupSyncUrl) {
                    try {
                        await axios.post(groupSyncUrl, {
                            type: 'GROUP_SEEN',
                            groupJid: remoteJid,
                            title: msg.pushName || undefined,
                            receiver: sessionId,
                            participantSender: formatWebhookSender(msg),
                        })
                    } catch (err) {
                        console.error('❌ Group sync webhook error:', err.message)
                    }
                }
                continue
            }

            const content = resolveMessageContent(msg.message)
            if (!content) {
                if (process.env.WA_DEBUG_MESSAGE === '1') {
                    console.log('⚠️ Skipped: no extractable content', JSON.stringify(Object.keys(msg.message || {})))
                }
                continue
            }

            const messageType = getContentType(content)
            if (!messageType) {
                continue
            }

            const skipTypes = new Set([
                'reactionMessage',
                'protocolMessage',
                'senderKeyDistributionMessage',
            ])
            if (skipTypes.has(messageType)) {
                continue
            }

            try {
                let mediaUrl = null

                if (['imageMessage', 'videoMessage', 'documentMessage', 'audioMessage', 'stickerMessage', 'ptvMessage'].includes(messageType)) {
                    const media = await getMessageMedia(wa, msg)

                    if (media && media.base64) {
                        const ext = media.mimetype.split('/')[1] || 'bin'
                        const fileName = `${Date.now()}-${Math.random().toString(36).substring(7)}.${ext}`
                        const filePath = `/var/www/whatsender/public/uploads/wp/${fileName}`

                        fs.writeFileSync(filePath, Buffer.from(media.base64, 'base64'))

                        mediaUrl = `https://ronibot.com/uploads/wp/${fileName}`
                    }
                }

                const url = `${process.env.APP_WEBHOOK_URL}/send-webhook/${sessionId}`

                console.log('🚀 Sending webhook:', url, { type: m.type, messageType })

                await axios.post(url, {
                    payload: {
                        type: 'MESSAGE_RECEIVED',
                        data: [
                            {
                                ...msg,
                                media: {
                                    url: mediaUrl,
                                    type: messageType,
                                },
                            },
                        ],
                    },
                    sender: formatWebhookSender(msg),
                    receiver: sessionId,
                })
            } catch (err) {
                console.error('❌ Webhook error:', err.message)
            }
        }
    })
  

    wa.ev.on('messages.update', async (m) => {
        for (const { key, update } of m) {
            const msg = await getMessage(key)

            if (!msg) {
                continue
            }

            update.status = WAMessageStatus[update.status]
            const messagesUpdate = [
                {
                    key,
                    update,
                    message: msg,
                },
            ]
           
        }
    })

    wa.ev.on('message-receipt.update', async (m) => {
        for (const { key, messageTimestamp, pushName, broadcast, update } of m) {
            if (update?.pollUpdates) {
                const pollCreation = await getMessage(key)
                if (pollCreation) {
                    const pollMessage = await getAggregateVotesInPollMessage({
                        message: pollCreation,
                        pollUpdates: update.pollUpdates,
                    })
                    update.pollUpdates[0].vote = pollMessage
                    return
                }
            }
        }
    })

    wa.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update
        const statusCode = lastDisconnect?.error?.output?.statusCode

        if (connection === 'open') {
            retries.delete(sessionId)
        }

        if (connection === 'close') {
            if (statusCode === DisconnectReason.loggedOut || !shouldReconnect(sessionId)) {
                if (res && !res.headersSent) {
                    response(res, 500, false, 'Unable to create session.')
                }

                return deleteSession(sessionId)
            }

            setTimeout(
                () => {
                    createSession(sessionId, res)
                },
                statusCode === DisconnectReason.restartRequired ? 0 : parseInt(process.env.WA_SERVER_RECONNECT_INTERVAL ?? 0),
            )
        }

        if (qr) {
            if (res && !res.headersSent) {
                try {
                    const qrcode = await toDataURL(qr)
                    response(res, 200, true, 'QR code received, please scan the QR code.', { qrcode })
                    return
                } catch {
                    response(res, 500, false, 'Unable to create QR code.')
                }
            }

            try {
                await wa.logout()
            } catch {
            } finally {
                deleteSession(sessionId)
            }
        }
    })

   

    async function getMessage(key) {
        if (store) {
            const msg = await store.loadMessages(key.remoteJid, key.id)
            return msg?.message || undefined
        }

        // Only if store is present
        return proto.Message.fromObject({})
    }
}

/**
 * @returns {(import('baileys').AnyWASocket|null)}
 */
const getSession = (sessionId) => {
    return sessions.get(sessionId) ?? null
}

const getListSessions = () => {
    return [...sessions.keys()]
}

const deleteSession = (sessionId) => {
    const sessionFile = 'md_' + sessionId
    const storeFile = `${sessionId}_store.json`
    const rmOptions = { force: true, recursive: true }

    rmSync(sessionsDir(sessionFile), rmOptions)
    rmSync(sessionsDir(storeFile), rmOptions)

    sessions.delete(sessionId)
    retries.delete(sessionId)
}

const getChatList = (sessionId, isGroup = false) => {
    const filter = isGroup ? '@g.us' : '@s.whatsapp.net'
    const chats = getSession(sessionId).store.chats
    return [...chats.values()].filter(chat => chat.id.endsWith(filter))
}

/**
 * @param {import('baileys').AnyWASocket} session
 */
const isExists = async (session, jid, isGroup = false) => {
    try {
        let result

        if (isGroup) {
            result = await session.groupMetadata(jid)

            return Boolean(result.id)
        }

        ;[result] = await session.onWhatsApp(jid)

        return result.exists
    } catch {
        return false
    }
}

/**
 * @param {import('baileys').AnyWASocket} session
 */
const sendMessage = async (session, receiver, message, options = {}, delayMs = 1000) => {
    try {
        await delay(parseInt(delayMs))
        return await session.sendMessage(receiver, message, options)
    } catch {
        return Promise.reject(null) // eslint-disable-line prefer-promise-reject-errors
    }
}

/**
 * @param {import('baileys').AnyWASocket} session
 */
const updateProfileStatus = async (session, status) => {
    try {
        return await session.updateProfileStatus(status)
    } catch {
        return Promise.reject(null) // eslint-disable-line prefer-promise-reject-errors
    }
}

const updateProfileName = async (session, name) => {
    try {
        return await session.updateProfileName(name)
    } catch {
        return Promise.reject(null) // eslint-disable-line prefer-promise-reject-errors
    }
}

const getProfilePicture = async (session, jid, type = 'image') => {
    try {
        return await session.profilePictureUrl(jid, type)
    } catch {
        return Promise.reject(null) // eslint-disable-line prefer-promise-reject-errors
    }
}

const blockAndUnblockUser = async (session, jid, block) => {
    try {
        return await session.updateBlockStatus(jid, block)
    } catch {
        return Promise.reject(null) // eslint-disable-line prefer-promise-reject-errors
    }
}

const formatPhone = (phone) => {
    if (phone.endsWith('@s.whatsapp.net')) {
        return phone
    }

    let formatted = phone.replace(/\D/g, '')

    return (formatted += '@s.whatsapp.net')
}

const formatGroup = (group) => {
    if (group.endsWith('@g.us')) {
        return group
    }

    let formatted = group.replace(/[^\d-]/g, '')

    return (formatted += '@g.us')
}

const cleanup = () => {
    console.log('Running cleanup before exit.')

    sessions.forEach((session, sessionId) => {
        session.store.writeToFile(sessionsDir(`${sessionId}_store.json`))
    })
}

const getGroupsWithParticipants = async (session) => {
    return session.groupFetchAllParticipating()
}

const participantsUpdate = async (session, jid, participants, action) => {
    return session.groupParticipantsUpdate(jid, participants, action)
}

const updateSubject = async (session, jid, subject) => {
    return session.groupUpdateSubject(jid, subject)
}

const updateDescription = async (session, jid, description) => {
    return session.groupUpdateDescription(jid, description)
}

const settingUpdate = async (session, jid, settings) => {
    return session.groupSettingUpdate(jid, settings)
}

const leave = async (session, jid) => {
    return session.groupLeave(jid)
}

const inviteCode = async (session, jid) => {
    return session.groupInviteCode(jid)
}

const revokeInvite = async (session, jid) => {
    return session.groupRevokeInvite(jid)
}

const metaData = async (session, req) => {
    return session.groupMetadata(req.groupId)
}

const acceptInvite = async (session, req) => {
    return session.groupAcceptInvite(req.invite)
}

const profilePicture = async (session, jid, urlImage) => {
    const image = await downloadImage(urlImage)
    return session.updateProfilePicture(jid, { url: image })
}

const readMessage = async (session, keys) => {
    return session.readMessages(keys)
}

const getStoreMessage = async (session, messageId, remoteJid) => {
    try {
        return await session.store.loadMessages(remoteJid, messageId)
    } catch {
        // eslint-disable-next-line prefer-promise-reject-errors
        return Promise.reject(null)
    }
}

const getMessageMedia = async (session, message) => {
    try {
        const inner = resolveMessageContent(message.message)
        if (!inner) {
            return Promise.reject(null)
        }
        const messageType = getContentType(inner) || Object.keys(inner)[0]
        const mediaMessage = inner[messageType]
        const buffer = await downloadMediaMessage(
            message,
            'buffer',
            {},
            { reuploadRequest: session.updateMediaMessage },
        )

        return {
            messageType,
            fileName: mediaMessage.fileName ?? '',
            caption: mediaMessage.caption ?? '',
            size: {
                fileLength: mediaMessage.fileLength,
                height: mediaMessage.height ?? 0,
                width: mediaMessage.width ?? 0,
            },
            mimetype: mediaMessage.mimetype,
            base64: buffer.toString('base64'),
        }
    } catch {
        // eslint-disable-next-line prefer-promise-reject-errors
        return Promise.reject(null)
    }
}

const convertToBase64 = (arrayBytes) => {
    const byteArray = new Uint8Array(arrayBytes)
    return Buffer.from(byteArray).toString('base64')
}

console.log('INIT STARTED')
const init = () => {
console.log('INIT RUNNING')
    readdir(sessionsDir(), (err, files) => {
        if (err) {
            throw err
        }

        for (const file of files) {
            if ((!file.startsWith('md_') && !file.startsWith('legacy_')) || file.endsWith('_store')) {
                continue
            }

            const filename = file.replace('.json', '')
            const sessionId = filename.substring(3)
            console.log('Recovering session: ' + sessionId)
            createSession(sessionId)
console.log('SESSION CREATED')
        }
    })
}

export {
    isSessionExists,
    createSession,
    getSession,
    getListSessions,
    deleteSession,
    getChatList,
    getGroupsWithParticipants,
    isExists,
    sendMessage,
    updateProfileStatus,
    updateProfileName,
    getProfilePicture,
    formatPhone,
    formatGroup,
    cleanup,
    participantsUpdate,
    updateSubject,
    updateDescription,
    settingUpdate,
    leave,
    inviteCode,
    revokeInvite,
    metaData,
    acceptInvite,
    profilePicture,
    readMessage,
    init,
    isSessionConnected,
    getMessageMedia,
    getStoreMessage,
    blockAndUnblockUser,
}
