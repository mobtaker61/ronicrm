# اسکریپت آماده‌سازی پروژه برای GitHub
# این اسکریپت پروژه را برای push به GitHub آماده می‌کند

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "آماده‌سازی پروژه RoniCRM برای GitHub" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# بررسی اینکه آیا repository از قبل وجود دارد
if (Test-Path ".git") {
    Write-Host "⚠️  Repository Git از قبل وجود دارد!" -ForegroundColor Yellow
    $continue = Read-Host "آیا می‌خواهید ادامه دهید؟ (y/n)"
    if ($continue -ne "y") {
        Write-Host "عملیات لغو شد." -ForegroundColor Red
        exit
    }
} else {
    Write-Host "✅ Initialize کردن Git repository..." -ForegroundColor Green
    git init
}

Write-Host ""
Write-Host "✅ بررسی وضعیت فایل‌ها..." -ForegroundColor Green
git status

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "مراحل بعدی:" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. فایل‌ها را اضافه کنید:" -ForegroundColor Yellow
Write-Host "   git add ." -ForegroundColor White
Write-Host ""
Write-Host "2. Commit اولیه را ایجاد کنید:" -ForegroundColor Yellow
Write-Host "   git commit -m 'Initial commit: RoniCRM project'" -ForegroundColor White
Write-Host ""
Write-Host "3. در GitHub یک repository جدید ایجاد کنید" -ForegroundColor Yellow
Write-Host ""
Write-Host "4. Remote را اضافه کنید:" -ForegroundColor Yellow
Write-Host "   git remote add origin https://github.com/YOUR_USERNAME/ronicrm.git" -ForegroundColor White
Write-Host ""
Write-Host "5. Branch را به main تغییر دهید:" -ForegroundColor Yellow
Write-Host "   git branch -M main" -ForegroundColor White
Write-Host ""
Write-Host "6. Push کنید:" -ForegroundColor Yellow
Write-Host "   git push -u origin main" -ForegroundColor White
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "برای اطلاعات بیشتر، فایل DEPLOY.md را مطالعه کنید." -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
