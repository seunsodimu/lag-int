<?php

namespace Laguna\Integration\Utils;

/**
 * URL Helper Utility
 * 
 * Provides utility functions for generating correct URLs based on the project's location.
 */
class UrlHelper
{
    /**
     * Attempt to read an explicitly configured base path from environment
     * Returns a normalized path like '/lag-int' or '' if not configured
     */
    private static function getConfiguredBasePath(): ?string
    {
        $env = $_ENV['APP_BASE_PATH'] ?? $_SERVER['APP_BASE_PATH'] ?? getenv('APP_BASE_PATH');
        if ($env === false || $env === null) {
            return null;
        }
        $env = trim((string)$env);
        if ($env === '' || $env === '/') {
            return '';
        }
        // Ensure leading slash, no trailing slash
        if ($env[0] !== '/') {
            $env = '/' . $env;
        }
        return rtrim($env, '/');
    }

    /**
     * Get the base URL for the project
     * 
     * @return string The base URL (e.g., '/lag-int')
     */
    public static function getBaseUrl(): string
    {
        // 1) Prefer explicit configuration via APP_BASE_PATH
        $configured = self::getConfiguredBasePath();
        if ($configured !== null) {
            return $configured; // may be '' (root) or '/lag-int'
        }

        // 2) Determine if public is the document root (Docker/Apache)
        $scriptFilename = $_SERVER['SCRIPT_FILENAME'] ?? '';
        $publicDir = realpath(__DIR__ . '/../../public');
        if ($publicDir && $scriptFilename) {
            $scriptDir = realpath(dirname($scriptFilename));
            if ($scriptDir === $publicDir) {
                // Served from web root; base is empty
                return '';
            }
        }

        // 3) If the script path includes /public/, use its parent as base (subdirectory deployments)
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($scriptName, '/public/') !== false) {
            $parts = explode('/public/', $scriptName);
            return rtrim($parts[0], '/');
        }

        // 4) Fallback using document root and project root
        $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        $projectRoot = dirname(dirname(__DIR__));

        if ($documentRoot) {
            $docRootNormalized = rtrim(str_replace('\\', '/', realpath($documentRoot)), '/');
            $projectRootNormalized = rtrim(str_replace('\\', '/', realpath($projectRoot)), '/');

            // If public is under document root, base is empty
            if ($publicDir && strpos($publicDir, $docRootNormalized) === 0) {
                return '';
            }

            // If project is under document root, return relative path
            if (strpos($projectRootNormalized, $docRootNormalized) === 0) {
                $relativePath = substr($projectRootNormalized, strlen($docRootNormalized));
                return rtrim($relativePath, '/');
            }
        }

        // Default: empty base
        return '';
    }
    
    /**
     * Get the public URL for the project
     * 
     * In Docker (public is DocumentRoot), if APP_BASE_PATH is set, we still want
     * URLs like '/lag-int/login.php'. So return the configured base when present.
     * 
     * @return string The base path for public URLs ('' or '/lag-int')
     */
    public static function getPublicUrl(): string
    {
        $configured = self::getConfiguredBasePath();
        if ($configured !== null) {
            return $configured; // '' (root) or '/lag-int'
        }

        $base = self::getBaseUrl();
        if ($base === '') {
            // Docker/Apache serves public as document root
            return '';
        }
        // When not at web root (e.g., Apache serving project subdir), the public URL sits under /public
        return $base . '/public';
    }
    
    /**
     * Generate a URL for a public page
     * 
     * @param string $page The page name (e.g., 'login.php', 'index.php')
     * @return string The complete URL
     */
    public static function url(string $page): string
    {
        $public = self::getPublicUrl();
        // If public is '', we are at web root (Docker), build absolute path from '/'
        if ($public === '') {
            return '/' . ltrim($page, '/');
        }
        return $public . '/' . ltrim($page, '/');
    }
    
    /**
     * Generate a URL for a project file (non-public)
     * 
     * @param string $path The relative path from project root
     * @return string The complete URL
     */
    public static function projectUrl(string $path): string
    {
        $base = self::getBaseUrl();
        return ($base === '' ? '' : $base) . '/' . ltrim($path, '/');
    }
    
    /**
     * Get the current page URL
     * 
     * @return string The current page URL
     */
    public static function getCurrentUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        
        return $protocol . $host . $uri;
    }
    
    /**
     * Redirect to a public page
     * 
     * @param string $page The page name
     * @param array $params Optional query parameters
     * @return void
     */
    public static function redirect(string $page, array $params = []): void
    {
        $url = self::url($page);
        
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Check if we're running in a subdirectory
     * 
     * @return bool True if in subdirectory, false if in document root
     */
    public static function isSubdirectory(): bool
    {
        return self::getBaseUrl() !== '';
    }
}