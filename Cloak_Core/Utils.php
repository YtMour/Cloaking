<?php
namespace Cloak\Core;

/**
 * 工具函数类
 * 提供各种通用的工具方法
 */
class Utils {
    
    /**
     * 格式化文件大小
     */
    public static function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * 格式化时间差
     */
    public static function formatTimeDiff($timestamp) {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return $diff . '秒前';
        } elseif ($diff < 3600) {
            return floor($diff / 60) . '分钟前';
        } elseif ($diff < 86400) {
            return floor($diff / 3600) . '小时前';
        } elseif ($diff < 2592000) {
            return floor($diff / 86400) . '天前';
        } else {
            return date('Y-m-d H:i:s', $timestamp);
        }
    }
    
    /**
     * 安全的HTML输出
     */
    public static function escapeHtml($string) {
        return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    /**
     * 生成随机字符串
     */
    public static function generateRandomString($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * 验证IP地址
     */
    public static function isValidIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * 验证URL
     */
    public static function isValidURL($url) {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * 截断字符串
     */
    public static function truncateString($string, $length = 100, $suffix = '...') {
        if (mb_strlen($string, 'UTF-8') <= $length) {
            return $string;
        }
        
        return mb_substr($string, 0, $length, 'UTF-8') . $suffix;
    }
    
    /**
     * 清理文件名
     */
    public static function sanitizeFilename($filename) {
        // 移除危险字符
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        // 移除多个连续的下划线
        $filename = preg_replace('/_+/', '_', $filename);
        // 移除开头和结尾的下划线
        return trim($filename, '_');
    }
    
    /**
     * 获取客户端浏览器信息
     */
    public static function getBrowserInfo($userAgent) {
        $browsers = [
            'Chrome' => '/Chrome\/([0-9.]+)/',
            'Firefox' => '/Firefox\/([0-9.]+)/',
            'Safari' => '/Safari\/([0-9.]+)/',
            'Edge' => '/Edge\/([0-9.]+)/',
            'Opera' => '/Opera\/([0-9.]+)/',
            'Internet Explorer' => '/MSIE ([0-9.]+)/'
        ];
        
        foreach ($browsers as $browser => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                return [
                    'name' => $browser,
                    'version' => $matches[1] ?? 'Unknown'
                ];
            }
        }
        
        return [
            'name' => 'Unknown',
            'version' => 'Unknown'
        ];
    }
    
    /**
     * 获取操作系统信息
     */
    public static function getOSInfo($userAgent) {
        $systems = [
            'Windows 11' => '/Windows NT 10.0.*Win64.*x64/',
            'Windows 10' => '/Windows NT 10.0/',
            'Windows 8.1' => '/Windows NT 6.3/',
            'Windows 8' => '/Windows NT 6.2/',
            'Windows 7' => '/Windows NT 6.1/',
            'macOS' => '/Mac OS X ([0-9_]+)/',
            'Linux' => '/Linux/',
            'Android' => '/Android ([0-9.]+)/',
            'iOS' => '/iPhone OS ([0-9_]+)/',
            'iPad' => '/iPad.*OS ([0-9_]+)/'
        ];
        
        foreach ($systems as $os => $pattern) {
            if (preg_match($pattern, $userAgent, $matches)) {
                $version = isset($matches[1]) ? str_replace('_', '.', $matches[1]) : '';
                return [
                    'name' => $os,
                    'version' => $version
                ];
            }
        }
        
        return [
            'name' => 'Unknown',
            'version' => 'Unknown'
        ];
    }
    
    /**
     * 检查是否为移动设备
     */
    public static function isMobile($userAgent) {
        $mobileKeywords = [
            'Mobile', 'Android', 'iPhone', 'iPad', 'iPod', 'BlackBerry', 
            'Windows Phone', 'Opera Mini', 'IEMobile'
        ];
        
        foreach ($mobileKeywords as $keyword) {
            if (stripos($userAgent, $keyword) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 生成分页HTML
     */
    public static function generatePagination($currentPage, $totalPages, $baseUrl, $params = []) {
        if ($totalPages <= 1) {
            return '';
        }
        
        $html = '<div class="pagination">';
        
        // 上一页
        if ($currentPage > 1) {
            $prevParams = array_merge($params, ['page' => $currentPage - 1]);
            $prevUrl = $baseUrl . '?' . http_build_query($prevParams);
            $html .= "<a href=\"$prevUrl\" class=\"page-btn prev\">‹ 上一页</a>";
        }
        
        // 页码
        $start = max(1, $currentPage - 2);
        $end = min($totalPages, $currentPage + 2);
        
        if ($start > 1) {
            $firstParams = array_merge($params, ['page' => 1]);
            $firstUrl = $baseUrl . '?' . http_build_query($firstParams);
            $html .= "<a href=\"$firstUrl\" class=\"page-btn\">1</a>";
            if ($start > 2) {
                $html .= "<span class=\"page-dots\">...</span>";
            }
        }
        
        for ($i = $start; $i <= $end; $i++) {
            if ($i == $currentPage) {
                $html .= "<span class=\"page-btn current\">$i</span>";
            } else {
                $pageParams = array_merge($params, ['page' => $i]);
                $pageUrl = $baseUrl . '?' . http_build_query($pageParams);
                $html .= "<a href=\"$pageUrl\" class=\"page-btn\">$i</a>";
            }
        }
        
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= "<span class=\"page-dots\">...</span>";
            }
            $lastParams = array_merge($params, ['page' => $totalPages]);
            $lastUrl = $baseUrl . '?' . http_build_query($lastParams);
            $html .= "<a href=\"$lastUrl\" class=\"page-btn\">$totalPages</a>";
        }
        
        // 下一页
        if ($currentPage < $totalPages) {
            $nextParams = array_merge($params, ['page' => $currentPage + 1]);
            $nextUrl = $baseUrl . '?' . http_build_query($nextParams);
            $html .= "<a href=\"$nextUrl\" class=\"page-btn next\">下一页 ›</a>";
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 生成表格排序链接
     */
    public static function generateSortLink($column, $currentSort, $currentOrder, $baseUrl, $params = []) {
        $newOrder = ($currentSort === $column && $currentOrder === 'asc') ? 'desc' : 'asc';
        $sortParams = array_merge($params, ['sort' => $column, 'order' => $newOrder]);
        $sortUrl = $baseUrl . '?' . http_build_query($sortParams);
        
        $arrow = '';
        if ($currentSort === $column) {
            $arrow = $currentOrder === 'asc' ? ' ↑' : ' ↓';
        }
        
        return "<a href=\"$sortUrl\" class=\"sort-link\">$column$arrow</a>";
    }
    
    /**
     * 验证CSRF令牌
     */
    public static function validateCSRF($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * 生成成功消息HTML
     */
    public static function successMessage($message) {
        return "<div class=\"alert alert-success\">✅ $message</div>";
    }
    
    /**
     * 生成错误消息HTML
     */
    public static function errorMessage($message) {
        return "<div class=\"alert alert-error\">❌ $message</div>";
    }
    
    /**
     * 生成警告消息HTML
     */
    public static function warningMessage($message) {
        return "<div class=\"alert alert-warning\">⚠️ $message</div>";
    }
    
    /**
     * 生成信息消息HTML
     */
    public static function infoMessage($message) {
        return "<div class=\"alert alert-info\">ℹ️ $message</div>";
    }
    
    /**
     * 检查文件是否可写
     */
    public static function isWritable($file) {
        if (file_exists($file)) {
            return is_writable($file);
        } else {
            return is_writable(dirname($file));
        }
    }
    
    /**
     * 创建目录（如果不存在）
     */
    public static function ensureDirectory($path) {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        return true;
    }
    
    /**
     * 获取文件扩展名
     */
    public static function getFileExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * 检查是否为AJAX请求
     */
    public static function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * JSON响应
     */
    public static function jsonResponse($data, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }
}
?>
