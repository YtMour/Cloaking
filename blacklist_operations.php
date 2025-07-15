<?php
/**
 * 黑名单操作模块
 * 处理IP和UA黑名单的添加、删除、检查等操作，支持批量操作
 */

class BlacklistOperations {
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
    }
    
    /**
     * 检查IP是否在黑名单中
     */
    public function isIPInBlacklist($ip) {
        // 检查独立IP文件
        if (file_exists($this->config['ip_file'])) {
            $ip_list = file($this->config['ip_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (in_array($ip, array_map('trim', $ip_list))) {
                return true;
            }
        }

        // 检查UA文件中的IP
        if (file_exists($this->config['ua_file'])) {
            $ua_lines = file($this->config['ua_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($ua_lines as $line) {
                if (preg_match('/\[ip:([^\]]+)\]$/', trim($line), $matches)) {
                    if (trim($matches[1]) === $ip) {
                        return true;
                    }
                }
            }
        }

        // 检查云服务器IP前缀
        $cloud_ip_prefix = ['34.', '35.', '66.249.', '104.28.', '54.'];
        foreach ($cloud_ip_prefix as $prefix) {
            if (strpos($ip, $prefix) === 0) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * 检查UA是否在黑名单中
     */
    public function isUAInBlacklist($ua) {
        if (!file_exists($this->config['ua_file'])) {
            return false;
        }

        $ua_lower = strtolower($ua);
        $ua_lines = file($this->config['ua_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($ua_lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // 处理混合格式，提取UA部分
            if (preg_match('/^(.+?)\s*\[ip:([^\]]+)\]$/', $line, $matches)) {
                $line_ua = strtolower(trim($matches[1]));
            } else {
                $line_ua = strtolower($line);
            }

            // 检查是否匹配
            if (!empty($line_ua) && strpos($ua_lower, $line_ua) !== false) {
                return true;
            }
        }

        return false;
    }
    
    /**
     * 添加IP到黑名单
     */
    public function addIPToBlacklist($ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => '无效的IP地址格式'];
        }
        
        $existing_ips = file_exists($this->config['ip_file']) ?
            file($this->config['ip_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        if (in_array($ip, array_map('trim', $existing_ips))) {
            return ['success' => false, 'message' => "IP地址 {$ip} 已存在于黑名单中"];
        }

        if (file_put_contents($this->config['ip_file'], $ip . "\n", FILE_APPEND | LOCK_EX) !== false) {
            return ['success' => true, 'message' => "IP地址 {$ip} 已添加到黑名单"];
        } else {
            return ['success' => false, 'message' => '添加失败，请检查文件权限'];
        }
    }
    
    /**
     * 添加UA到黑名单
     */
    public function addUAToBlacklist($ua) {
        if (empty($ua)) {
            return ['success' => false, 'message' => 'User Agent 不能为空'];
        }
        
        $existing_uas = file_exists($this->config['ua_file']) ?
            file($this->config['ua_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        if (in_array($ua, array_map('trim', $existing_uas))) {
            return ['success' => false, 'message' => '该 User Agent 已存在于黑名单中'];
        }

        if (file_put_contents($this->config['ua_file'], $ua . "\n", FILE_APPEND | LOCK_EX) !== false) {
            return ['success' => true, 'message' => 'User Agent 已添加到黑名单'];
        } else {
            return ['success' => false, 'message' => '添加失败，请检查文件权限'];
        }
    }
    
    /**
     * 同时添加IP和UA到黑名单
     */
    public function addBothToBlacklist($ip, $ua) {
        $results = [];
        
        // 添加IP
        $ip_result = $this->addIPToBlacklist($ip);
        $results['ip'] = $ip_result;
        
        // 添加UA
        $ua_result = $this->addUAToBlacklist($ua);
        $results['ua'] = $ua_result;
        
        // 生成综合消息
        $messages = [];
        if ($ip_result['success']) {
            $messages[] = "✅ IP: " . $ip_result['message'];
        } else {
            $messages[] = "❌ IP: " . $ip_result['message'];
        }
        
        if ($ua_result['success']) {
            $messages[] = "✅ UA: " . $ua_result['message'];
        } else {
            $messages[] = "❌ UA: " . $ua_result['message'];
        }
        
        return [
            'success' => $ip_result['success'] || $ua_result['success'],
            'message' => implode('<br>', $messages),
            'details' => $results
        ];
    }
    
    /**
     * 批量添加IP到黑名单
     */
    public function batchAddIPs($ips) {
        $results = [];
        $success_count = 0;
        
        foreach ($ips as $ip) {
            $result = $this->addIPToBlacklist($ip);
            $results[] = $result;
            if ($result['success']) {
                $success_count++;
            }
        }
        
        return [
            'success' => $success_count > 0,
            'message' => "批量操作完成：成功添加 {$success_count} 个IP，失败 " . (count($ips) - $success_count) . " 个",
            'details' => $results
        ];
    }
    
    /**
     * 批量添加UA到黑名单
     */
    public function batchAddUAs($uas) {
        $results = [];
        $success_count = 0;
        
        foreach ($uas as $ua) {
            $result = $this->addUAToBlacklist($ua);
            $results[] = $result;
            if ($result['success']) {
                $success_count++;
            }
        }
        
        return [
            'success' => $success_count > 0,
            'message' => "批量操作完成：成功添加 {$success_count} 个UA，失败 " . (count($uas) - $success_count) . " 个",
            'details' => $results
        ];
    }
    
    /**
     * 批量同时添加IP和UA到黑名单
     */
    public function batchAddBoth($items) {
        $results = [];
        $ip_success = 0;
        $ua_success = 0;
        
        foreach ($items as $item) {
            $result = $this->addBothToBlacklist($item['ip'], $item['ua']);
            $results[] = $result;
            
            if ($result['details']['ip']['success']) {
                $ip_success++;
            }
            if ($result['details']['ua']['success']) {
                $ua_success++;
            }
        }
        
        return [
            'success' => $ip_success > 0 || $ua_success > 0,
            'message' => "批量操作完成：成功添加 {$ip_success} 个IP，{$ua_success} 个UA",
            'details' => $results
        ];
    }
    
    /**
     * 从IP黑名单移除
     */
    public function removeIPFromBlacklist($ip) {
        if (!file_exists($this->config['ip_file'])) {
            return ['success' => false, 'message' => 'IP黑名单文件不存在'];
        }
        
        $existing_ips = file($this->config['ip_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $filtered_ips = array_filter($existing_ips, function($line) use ($ip) {
            return trim($line) !== $ip;
        });

        if (count($filtered_ips) < count($existing_ips)) {
            file_put_contents($this->config['ip_file'], implode("\n", $filtered_ips) . "\n");
            return ['success' => true, 'message' => "IP地址 {$ip} 已从黑名单移除"];
        } else {
            return ['success' => false, 'message' => "IP地址 {$ip} 不在黑名单中"];
        }
    }
    
    /**
     * 从UA黑名单移除
     */
    public function removeUAFromBlacklist($ua) {
        if (!file_exists($this->config['ua_file'])) {
            return ['success' => false, 'message' => 'UA黑名单文件不存在'];
        }
        
        $existing_uas = file($this->config['ua_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $filtered_uas = array_filter($existing_uas, function($line) use ($ua) {
            // 处理混合格式，只比较UA部分
            $line_ua = trim($line);
            if (preg_match('/^(.+?)\s*\[ip:([^\]]+)\]$/', $line_ua, $matches)) {
                $line_ua = trim($matches[1]);
            }
            return $line_ua !== $ua;
        });

        if (count($filtered_uas) < count($existing_uas)) {
            file_put_contents($this->config['ua_file'], implode("\n", $filtered_uas) . "\n");
            return ['success' => true, 'message' => 'User Agent 已从黑名单移除'];
        } else {
            return ['success' => false, 'message' => '该 User Agent 不在黑名单中'];
        }
    }
}
