@echo off
REM Seamless React Events - WordPress Plugin Setup Script (Windows)
REM This script helps copy the React app build to the WordPress plugin

setlocal enabledelayedexpansion

echo =======================================
echo Seamless React Events - Setup Script
echo =======================================
echo.

REM Check if running from correct directory
if not exist "seamless.php" (
    echo Error: seamless.php not found!
    echo Please run this script from the plugin directory.
    pause
    exit /b 1
)

echo Plugin directory detected
echo.

REM Create react-app directory if it doesn't exist
if not exist "react-app" (
    echo Creating react-app directory...
    mkdir react-app
    echo Directory created
)

REM Check if dist folder exists in parent directory
if not exist "..\dist" (
    echo.
    echo Error: React app dist folder not found at ..\dist
    echo.
    echo Please build the React app first:
    echo   1. cd ..
    echo   2. npm install
    echo   3. npm run build
    echo.
    pause
    exit /b 1
)

echo Found React app build at ..\dist
echo.

REM Copy dist folder
echo Copying dist folder...
if exist "react-app\dist" rmdir /s /q "react-app\dist"
xcopy /e /i /y "..\dist" "react-app\dist" >nul

if %errorlevel% equ 0 (
    echo Files copied successfully!
) else (
    echo Error copying files!
    pause
    exit /b 1
)

echo.
echo Setup complete!
echo.
echo Next steps:
echo 1. Copy the 'seamless' folder to your WordPress plugins directory:
echo    wp-content/plugins/seamless/
echo.
echo 2. Activate the plugin in WordPress Admin - Plugins
echo.
echo 3. Configure API endpoint:
echo    WordPress Admin - Settings - React Events
echo.
echo 4. Add shortcode to a page:
echo    [seamless_react_events]
echo.
echo 5. Ensure CORS is enabled on your API server
echo.
echo =======================================
pause
