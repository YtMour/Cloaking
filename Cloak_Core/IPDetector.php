<?php
namespace Cloak\Core;

/**
 * IP检测模块
 * 负责获取访客真实IP地址，支持CDN环境
 */
class IPDetector {
    
    // IP检测头部优先级（从高到低）
    private $headers = [
        'HTTP_X_REAL_IP',           // Nginx代理 - 最高优先级
        'HTTP_X_FORWARDED_FOR',     // 代理/负载均衡
        'HTTP_CLIENT_IP',           // 客户端IP
        'HTTP_CF_CONNECTING_IP',    // Cloudflare（如果使用）
        'HTTP_TRUE_CLIENT_IP',      // 某些CDN使用
        'HTTP_X_CLUSTER_CLIENT_IP', // 集群环境
        'REMOTE_ADDR'               // 直接连接IP - 最低优先级
    ];
    
    // 内网IP范围
    private $privateRanges = [
        // IPv4 内网范围
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16',

        // IPv6 内网范围
        'fc00::/7',     // 唯一本地地址
        'fe80::/10',    // 链路本地地址
        '::1/128',      // 回环地址
        '::/128',       // 未指定地址
        'ff00::/8',     // 多播地址
        '2001:db8::/32' // 文档用途地址
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

            // 优先返回第一个有效的公网IP
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if ($this->isValidIP($ip) && $this->isValidPublicIP($ip)) {
                    return $ip;
                }
            }

            // 如果没有公网IP，返回第一个有效IP
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
        // 首先检查是否为有效的IP地址格式
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        // 对于IPv6，需要特殊处理
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            // IPv6地址都认为是有效的，包括内网IPv6
            return true;
        }

        // 对于IPv4，检查是否为公网IP或者允许内网IP
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
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
        
        // IPv6处理
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) &&
            filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $this->ipv6InRange($ip, $subnet, (int)$mask);
        }
        
        return false;
    }

    /**
     * IPv6范围检查
     */
    private function ipv6InRange($ip, $subnet, $mask) {
        // 将IPv6地址转换为二进制
        $ip_bin = inet_pton($ip);
        $subnet_bin = inet_pton($subnet);

        if ($ip_bin === false || $subnet_bin === false) {
            return false;
        }

        // 计算需要比较的字节数
        $bytes_to_check = intval($mask / 8);
        $bits_to_check = $mask % 8;

        // 比较完整字节
        for ($i = 0; $i < $bytes_to_check; $i++) {
            if ($ip_bin[$i] !== $subnet_bin[$i]) {
                return false;
            }
        }

        // 比较剩余位
        if ($bits_to_check > 0 && $bytes_to_check < 16) {
            $mask_byte = 0xFF << (8 - $bits_to_check);
            if ((ord($ip_bin[$bytes_to_check]) & $mask_byte) !==
                (ord($subnet_bin[$bytes_to_check]) & $mask_byte)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 标准化IPv6地址显示
     */
    private function normalizeIPv6($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return $ip;
        }

        // 使用inet_pton和inet_ntop来标准化IPv6地址
        $binary = inet_pton($ip);
        if ($binary === false) {
            return $ip;
        }

        return inet_ntop($binary);
    }

    /**
     * 检查IPv6地址类型
     */
    private function getIPv6Type($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return 'not_ipv6';
        }

        // 标准化地址
        $normalized = $this->normalizeIPv6($ip);

        // 检查各种IPv6地址类型
        if ($normalized === '::1') {
            return 'loopback';
        }

        if (strpos($normalized, 'fe80:') === 0) {
            return 'link_local';
        }

        if (strpos($normalized, 'fc') === 0 || strpos($normalized, 'fd') === 0) {
            return 'unique_local';
        }

        if (strpos($normalized, 'ff') === 0) {
            return 'multicast';
        }

        if (strpos($normalized, '2001:db8:') === 0) {
            return 'documentation';
        }

        if ($normalized === '::') {
            return 'unspecified';
        }

        // 检查是否为全局单播地址
        if (strpos($normalized, '2') === 0 || strpos($normalized, '3') === 0) {
            return 'global_unicast';
        }

        return 'other';
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

    /**
     * 获取详细的IP检测信息（用于调试）
     */
    public function getDetailedIPInfo() {
        $info = [
            'detected_ip' => $this->getRealIP(),
            'all_headers' => [],
            'detection_order' => [],
            'analysis' => []
        ];

        // 记录所有相关头部
        foreach ($this->headers as $header) {
            if (isset($_SERVER[$header])) {
                $info['all_headers'][$header] = $_SERVER[$header];
            }
        }

        // 记录检测顺序和结果
        foreach ($this->headers as $index => $header) {
            $ip = $this->extractValidIP($header);
            $ipInfo = [
                'priority' => $index + 1,
                'header' => $header,
                'raw_value' => $_SERVER[$header] ?? null,
                'extracted_ip' => $ip,
                'is_valid' => $ip ? $this->isValidIP($ip) : false,
                'is_public' => $ip ? $this->isValidPublicIP($ip) : false,
                'is_private' => $ip ? $this->isPrivateIP($ip) : false,
                'is_cloud' => $ip ? $this->isCloudProvider($ip) : false,
                'ip_version' => null,
                'ipv6_type' => null,
                'normalized_ip' => $ip
            ];

            if ($ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    $ipInfo['ip_version'] = 'IPv4';
                } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $ipInfo['ip_version'] = 'IPv6';
                    $ipInfo['ipv6_type'] = $this->getIPv6Type($ip);
                    $ipInfo['normalized_ip'] = $this->normalizeIPv6($ip);
                }
            }

            $info['detection_order'][] = $ipInfo;

            if ($ip && $this->isValidPublicIP($ip)) {
                break; // 找到第一个有效公网IP就停止
            }
        }

        return $info;
    }
}
?>
