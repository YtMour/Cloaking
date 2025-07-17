<?php
namespace Cloak\Core;

/**
 * IP检测模块
 * 负责获取访客真实IP地址，支持CDN环境
 */
class IPDetector {
    
    // IP检测头部优先级（从高到低）
    private $headers = [
        'HTTP_CF_CONNECTING_IP',    // Cloudflare
        'HTTP_X_FORWARDED_FOR',     // 代理/负载均衡
        'HTTP_X_REAL_IP',           // Nginx代理
        'HTTP_CLIENT_IP',           // 客户端IP
        'REMOTE_ADDR'               // 直接连接IP
    ];
    
    // 内网IP范围
    private $privateRanges = [
        '10.0.0.0/8',
        '172.16.0.0/12', 
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16',
        'fc00::/7',
        'fe80::/10',
        '::1/128'
    ];
    
    /**
     * 获取访客真实IP
     */
    public function getRealIP() {
        foreach ($this->headers as $header) {
            $ip = $this->extractValidIP($header);
            if ($ip && $this->isValidPublicIP($ip)) {
                return $ip;
            }
        }
        
        // 如果没有找到公网IP，返回最后一个有效IP
        foreach ($this->headers as $header) {
            $ip = $this->extractValidIP($header);
            if ($ip) {
                return $ip;
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * 从HTTP头部提取有效IP
     */
    private function extractValidIP($header) {
        if (!isset($_SERVER[$header])) {
            return null;
        }
        
        $value = $_SERVER[$header];
        
        // 处理多个IP的情况（用逗号分隔）
        if (strpos($value, ',') !== false) {
            $ips = explode(',', $value);
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if ($this->isValidIP($ip)) {
                    return $ip;
                }
            }
        } else {
            $ip = trim($value);
            if ($this->isValidIP($ip)) {
                return $ip;
            }
        }
        
        return null;
    }
    
    /**
     * 验证IP地址格式
     */
    private function isValidIP($ip) {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            || filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }
    
    /**
     * 检查是否为有效的公网IP
     */
    private function isValidPublicIP($ip) {
        // 验证IP格式
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        
        // 检查是否为内网IP
        return !$this->isPrivateIP($ip);
    }
    
    /**
     * 检查是否为内网IP
     */
    private function isPrivateIP($ip) {
        foreach ($this->privateRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * 检查IP是否在指定范围内
     */
    private function ipInRange($ip, $range) {
        if (strpos($range, '/') === false) {
            return $ip === $range;
        }
        
        list($subnet, $mask) = explode('/', $range);
        
        // IPv4处理
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return false;
            }
            
            $ip_long = ip2long($ip);
            $subnet_long = ip2long($subnet);
            $mask_long = -1 << (32 - (int)$mask);
            
            return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
        }
        
        // IPv6处理（简化版）
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // 简单的IPv6范围检查
            return strpos($ip, substr($subnet, 0, strpos($subnet, ':'))) === 0;
        }
        
        return false;
    }
    
    /**
     * 获取IP地理位置信息（可扩展）
     */
    public function getIPLocation($ip) {
        // 这里可以集成第三方IP地理位置服务
        // 如：ip-api.com, ipinfo.io 等
        
        return [
            'ip' => $ip,
            'country' => 'Unknown',
            'region' => 'Unknown',
            'city' => 'Unknown',
            'isp' => 'Unknown'
        ];
    }
    
    /**
     * 检查IP是否为已知的云服务提供商
     */
    public function isCloudProvider($ip) {
        $cloudRanges = [
            // AWS
            '3.0.0.0/8',
            '13.0.0.0/8',
            '18.0.0.0/8',
            '34.0.0.0/8',
            '35.0.0.0/8',
            '52.0.0.0/8',
            '54.0.0.0/8',
            
            // Google Cloud
            '34.64.0.0/10',
            '35.184.0.0/13',
            '35.192.0.0/14',
            '35.196.0.0/15',
            
            // Azure
            '13.64.0.0/11',
            '20.0.0.0/8',
            '40.0.0.0/8',
            '52.0.0.0/8',
            
            // Cloudflare
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '104.16.0.0/12',
            '108.162.192.0/18',
            '131.0.72.0/22',
            '141.101.64.0/18',
            '162.158.0.0/15',
            '172.64.0.0/13',
            '173.245.48.0/20',
            '188.114.96.0/20',
            '190.93.240.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17'
        ];
        
        foreach ($cloudRanges as $range) {
            if ($this->ipInRange($ip, $range)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * 获取所有可能的IP地址
     */
    public function getAllPossibleIPs() {
        $ips = [];
        
        foreach ($this->headers as $header) {
            if (isset($_SERVER[$header])) {
                $value = $_SERVER[$header];
                if (strpos($value, ',') !== false) {
                    $headerIPs = explode(',', $value);
                    foreach ($headerIPs as $ip) {
                        $ip = trim($ip);
                        if ($this->isValidIP($ip)) {
                            $ips[$header][] = $ip;
                        }
                    }
                } else {
                    $ip = trim($value);
                    if ($this->isValidIP($ip)) {
                        $ips[$header] = $ip;
                    }
                }
            }
        }
        
        return $ips;
    }
}
?>
