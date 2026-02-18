<?php

/**
 * MadelineProto IPC entry point (Laravel compatibility).
 *
 * WebRunner requires the IPC script to be inside document root (public/).
 * This wrapper includes the real entry.php from vendor so WebRunner's
 * assertion "runPath within absolute document root" passes.
 *
 * @see vendor/danog/madelineproto/src/Ipc/Runner/entry.php
 */
require_once __DIR__.'/../vendor/danog/madelineproto/src/Ipc/Runner/entry.php';
