<?php
namespace Cloak\Core;

/**
 * 黑名单检查模块
 * 负责检查IP和User-Agent是否在黑名单中
 */
class BlacklistChecker {
    private $config;
    private $uaCache = null;
    private $ipCache = null;
    private $cacheTime = 0;
    
    public function __construct() {
        $this->config = Config::getInstance();
    }
    
    /**
     * 检查是否为机器人
     */
    public function isBot($userAgent, $ip) {
        return $this->checkUA($userAgent) || $this->checkIP($ip);
    }
    
    /**
     * 检查User-Agent是否在黑名单中
     */
    public function checkUA($userAgent) {
        if (empty($userAgent)) {
            return true; // 空UA视为机器人
        }
        
        $userAgent = strtolower(trim($userAgent));
        $blacklist = $this->getUABlacklist();
        
        foreach ($blacklist as $pattern) {
            $pattern = strtolower(trim($pattern));
            
            // 跳过空行和注释
            if (empty($pattern) || strpos($pattern, '#') === 0) {
                continue;
            }
            
            // 处理混合格式：ua [ip:xxx.xxx.xxx.xxx]
            if (strpos($pattern, '[ip:') !== false) {
                $pattern = preg_replace('/\s*\[ip:[^\]]+\]/', '', $pattern);
                $pattern = trim($pattern);
            }
            
            // 检查是否匹配
            if ($this->matchPattern($userAgent, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 检查IP是否在黑名单中
     */
    public function checkIP($ip) {
        if (empty($ip) || $ip === 'unknown') {
            return false;
        }
        
        $blacklist = $this->getIPBlacklist();
        
        // 直接IP匹配
        if (in_array($ip, $blacklist)) {
            return true;
        }
        
        // 检查IP段匹配
        foreach ($blacklist as $blackIP) {
            if ($this->matchIPRange($ip, $blackIP)) {
                return true;
            }
        }
        
        // 从UA黑名单中提取IP进行检查
        $uaBlacklist = $this->getUABlacklist();
        foreach ($uaBlacklist as $line) {
            $extractedIP = $this->extractIPFromUALine($line);
            if ($extractedIP && $extractedIP === $ip) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 获取UA黑名单
     */
    private function getUABlacklist() {
        if ($this->shouldRefreshCache()) {
            $this->refreshCache();
        }
        
        return $this->uaCache ?? [];
    }
    
    /**
     * 获取IP黑名单
     */
    private function getIPBlacklist() {
        if ($this->shouldRefreshCache()) {
            $this->refreshCache();
        }
        
        return $this->ipCache ?? [];
    }
    
    /**
     * 检查是否需要刷新缓存
     */
    private function shouldRefreshCache() {
        return $this->uaCache === null || 
               $this->ipCache === null || 
               (time() - $this->cacheTime) > $this->config->get('cache_duration', 3600);
    }
    
    /**
     * 刷新缓存
     */
    private function refreshCache() {
        $uaFile = $this->config->getUABlacklistPath();
        $ipFile = $this->config->getIPBlacklistPath();
        
        $this->uaCache = [];
        $this->ipCache = [];
        
        // 加载UA黑名单
        if (file_exists($uaFile)) {
            $this->uaCache = file($uaFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }
        
        // 加载IP黑名单
        if (file_exists($ipFile)) {
            $this->ipCache = file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        }
        
        $this->cacheTime = time();
    }
    
    /**
     * 模式匹配
     */
    private function matchPattern($userAgent, $pattern) {
        // 精确匹配
        if ($userAgent === $pattern) {
            return true;
        }
        
        // 包含匹配
        if (strpos($userAgent, $pattern) !== false) {
            return true;
        }
        
        // 通配符匹配
        if (strpos($pattern, '*') !== false) {
            $regex = '/^' . str_replace(['*', '?'], ['.*', '.'], preg_quote($pattern, '/')) . '$/i';
            return preg_match($regex, $userAgent);
        }
        
        return false;
    }
    
    /**
     * IP范围匹配
     */
    private function matchIPRange($ip, $range) {
        // 如果包含CIDR表示法
        if (strpos($range, '/') !== false) {
            return $this->ipInCIDR($ip, $range);
        }
        
        // 如果包含通配符
        if (strpos($range, '*') !== false) {
            $pattern = str_replace('*', '.*', preg_quote($range, '/'));
            return preg_match('/^' . $pattern . '$/', $ip);
        }
        
        return false;
    }
    
    /**
     * 检查IP是否在CIDR范围内
     */
    private function ipInCIDR($ip, $cidr) {
        list($subnet, $mask) = explode('/', $cidr);
        
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || 
            !filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return false;
        }
        
        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask_long = -1 << (32 - (int)$mask);
        
        return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
    }
    
    /**
     * 从UA行中提取IP
     */
    private function extractIPFromUALine($line) {
        if (preg_match('/\[ip:([^\]]+)\]/', $line, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }
    
    /**
     * 添加UA到黑名单
     */
    public function addUAToBlacklist($userAgent, $ip = null) {
        $line = trim($userAgent);
        if ($ip) {
            $line .= " [ip:$ip]";
        }
        
        return $this->appendToFile($this->config->getUABlacklistPath(), $line);
    }
    
    /**
     * 添加IP到黑名单
     */
    public function addIPToBlacklist($ip) {
        return $this->appendToFile($this->config->getIPBlacklistPath(), trim($ip));
    }
    
    /**
     * 从黑名单中移除UA
     */
    public function removeUAFromBlacklist($userAgent) {
        return $this->removeLineFromFile($this->config->getUABlacklistPath(), $userAgent);
    }
    
    /**
     * 从黑名单中移除IP
     */
    public function removeIPFromBlacklist($ip) {
        return $this->removeLineFromFile($this->config->getIPBlacklistPath(), $ip);
    }
    
    /**
     * 向文件追加内容
     */
    private function appendToFile($file, $content) {
        $this->config->ensureDataDirectory();
        
        if (file_put_contents($file, $content . "\n", FILE_APPEND | LOCK_EX) !== false) {
            $this->refreshCache(); // 刷新缓存
            return true;
        }
        return false;
    }
    
    /**
     * 从文件中移除行
     */
    private function removeLineFromFile($file, $content) {
        if (!file_exists($file)) {
            return false;
        }
        
        $lines = file($file, FILE_IGNORE_NEW_LINES);
        $newLines = [];
        $removed = false;
        
        foreach ($lines as $line) {
            if (trim($line) !== trim($content)) {
                $newLines[] = $line;
            } else {
                $removed = true;
            }
        }
        
        if ($removed) {
            file_put_contents($file, implode("\n", $newLines) . "\n");
            $this->refreshCache(); // 刷新缓存
            return true;
        }
        
        return false;
    }
    
    /**
     * 获取黑名单统计信息
     */
    public function getStats() {
        $uaBlacklist = $this->getUABlacklist();
        $ipBlacklist = $this->getIPBlacklist();
        
        $uaCount = 0;
        $mixedCount = 0;
        
        foreach ($uaBlacklist as $line) {
            if (!empty(trim($line)) && strpos(trim($line), '#') !== 0) {
                if (strpos($line, '[ip:') !== false) {
                    $mixedCount++;
                } else {
                    $uaCount++;
                }
            }
        }
        
        return [
            'ua_count' => $uaCount,
            'mixed_count' => $mixedCount,
            'ip_count' => count(array_filter($ipBlacklist, function($line) {
                return !empty(trim($line)) && strpos(trim($line), '#') !== 0;
            })),
            'total_count' => $uaCount + $mixedCount + count($ipBlacklist)
        ];
    }
    
    /**
     * 清除缓存
     */
    public function clearCache() {
        $this->uaCache = null;
        $this->ipCache = null;
        $this->cacheTime = 0;
    }
}
?>
