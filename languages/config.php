<?php
// Language configuration and management

// Available languages
$available_languages = [
    'en' => [
        'name' => 'English',
        'flag' => '🇺🇸',
        'code' => 'en',
        'file' => 'en.php'
    ],
    'hi' => [
        'name' => 'हिंदी',
        'flag' => '🇮🇳',
        'code' => 'hi',
        'file' => 'hi.php'
    ]
];

// Default language
$default_language = 'en';

// Get current language from cookie or default
function getCurrentLanguage() {
    global $default_language;
    return $_COOKIE['bc_language'] ?? $default_language;
}

// Set language cookie
function setLanguage($language_code) {
    global $available_languages;
    
    if (isset($available_languages[$language_code])) {
        // Set cookie for 1 year
        setcookie('bc_language', $language_code, time() + (365 * 24 * 60 * 60), '/');
        $_COOKIE['bc_language'] = $language_code;
        return true;
    }
    return false;
}

// Load language file
function loadLanguage($language_code = null) {
    global $available_languages, $default_language;
    
    if ($language_code === null) {
        $language_code = getCurrentLanguage();
    }
    
    // Fallback to default if language not available
    if (!isset($available_languages[$language_code])) {
        $language_code = $default_language;
    }
    
    $language_file = __DIR__ . '/' . $available_languages[$language_code]['file'];
    
    if (file_exists($language_file)) {
        include $language_file;
        return $lang ?? [];
    }
    
    // Return empty array if file not found
    return [];
}

// Get translation
function t($key, $default = null) {
    global $lang;
    
    if (!isset($lang)) {
        $lang = loadLanguage();
    }
    
    return $lang[$key] ?? $default ?? $key;
}

// Handle language change request
if (isset($_GET['change_language'])) {
    $new_language = $_GET['change_language'];
    if (setLanguage($new_language)) {
        // Redirect to remove the parameter from URL
        $redirect_url = $_SERVER['PHP_SELF'];
        if (!empty($_SERVER['QUERY_STRING'])) {
            $query_params = $_GET;
            unset($query_params['change_language']);
            if (!empty($query_params)) {
                $redirect_url .= '?' . http_build_query($query_params);
            }
        }
        header("Location: $redirect_url");
        exit;
    }
}

// Load current language
$lang = loadLanguage();
?>
