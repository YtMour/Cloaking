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
        $ip_file = $this->config['ip_file'] ?? '../ip_blacklist.txt';
        if (file_exists($ip_file)) {
            $ip_list = file($ip_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (in_array($ip, array_map('trim', $ip_list))) {
                return true;
            }
        }

        // 检查UA文件中的IP
        $ua_file = $this->config['ua_file'] ?? '../ua_blacklist.txt';
        if (file_exists($ua_file)) {
            $ua_lines = file($ua_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
        $ua_file = $this->config['ua_file'] ?? '../ua_blacklist.txt';
        if (!file_exists($ua_file)) {
            return false;
        }

        $ua_lower = strtolower($ua);
        $ua_lines = file($ua_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
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
        
        $ip_file = $this->config['ip_file'] ?? '../ip_blacklist.txt';
        $existing_ips = file_exists($ip_file) ?
            file($ip_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        if (in_array($ip, array_map('trim', $existing_ips))) {
            return ['success' => false, 'message' => "IP地址 {$ip} 已存在于黑名单中"];
        }

        if (file_put_contents($ip_file, $ip . "\n", FILE_APPEND | LOCK_EX) !== false) {
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
            return ['success' => false, 'message' => 'User-Agent不能为空'];
        }
        
        $ua_file = $this->config['ua_file'] ?? '../ua_blacklist.txt';
        $existing_uas = file_exists($ua_file) ?
            file($ua_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

        // 检查是否已存在
        foreach ($existing_uas as $existing_ua) {
            $existing_ua = trim($existing_ua);
            if (preg_match('/^(.+?)\s*\[ip:([^\]]+)\]$/', $existing_ua, $matches)) {
                $existing_ua = trim($matches[1]);
            }
            if (strtolower($existing_ua) === strtolower($ua)) {
                return ['success' => false, 'message' => "User-Agent 已存在于黑名单中"];
            }
        }

        if (file_put_contents($ua_file, $ua . "\n", FILE_APPEND | LOCK_EX) !== false) {
            return ['success' => true, 'message' => "User-Agent 已添加到黑名单"];
        } else {
            return ['success' => false, 'message' => '添加失败，请检查文件权限'];
        }
    }
    
    /**
     * 同时添加IP和UA到黑名单
     */
    public function addBothToBlacklist($ip, $ua) {
        $ip_result = $this->addIPToBlacklist($ip);
        $ua_result = $this->addUAToBlacklist($ua);
        
        if ($ip_result['success'] && $ua_result['success']) {
            return ['success' => true, 'message' => "IP地址 {$ip} 和 User-Agent 已同时添加到黑名单"];
        } elseif ($ip_result['success']) {
            return ['success' => true, 'message' => "IP地址 {$ip} 已添加到黑名单，UA添加失败：" . $ua_result['message']];
        } elseif ($ua_result['success']) {
            return ['success' => true, 'message' => "User-Agent 已添加到黑名单，IP添加失败：" . $ip_result['message']];
        } else {
            return ['success' => false, 'message' => "添加失败 - IP：" . $ip_result['message'] . "，UA：" . $ua_result['message']];
        }
    }
    
    /**
     * 从黑名单移除IP
     */
    public function removeIPFromBlacklist($ip) {
        $ip_file = $this->config['ip_file'] ?? '../ip_blacklist.txt';
        if (!file_exists($ip_file)) {
            return ['success' => false, 'message' => 'IP黑名单文件不存在'];
        }

        $lines = file($ip_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $new_lines = [];
        $found = false;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== $ip) {
                $new_lines[] = $line;
            } else {
                $found = true;
            }
        }

        if (!$found) {
            return ['success' => false, 'message' => "IP地址 {$ip} 不在黑名单中"];
        }

        if (file_put_contents($ip_file, implode("\n", $new_lines) . "\n", LOCK_EX) !== false) {
            return ['success' => true, 'message' => "IP地址 {$ip} 已从黑名单移除"];
        } else {
            return ['success' => false, 'message' => '移除失败，请检查文件权限'];
        }
    }
    
    /**
     * 从黑名单移除UA
     */
    public function removeUAFromBlacklist($ua) {
        $ua_file = $this->config['ua_file'] ?? '../ua_blacklist.txt';
        if (!file_exists($ua_file)) {
            return ['success' => false, 'message' => 'UA黑名单文件不存在'];
        }

        $lines = file($ua_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $new_lines = [];
        $found = false;

        foreach ($lines as $line) {
            $line = trim($line);
            $line_ua = $line;
            
            // 处理混合格式
            if (preg_match('/^(.+?)\s*\[ip:([^\]]+)\]$/', $line, $matches)) {
                $line_ua = trim($matches[1]);
            }
            
            if (strtolower($line_ua) !== strtolower($ua)) {
                $new_lines[] = $line;
            } else {
                $found = true;
            }
        }

        if (!$found) {
            return ['success' => false, 'message' => "User-Agent 不在黑名单中"];
        }

        if (file_put_contents($ua_file, implode("\n", $new_lines) . "\n", LOCK_EX) !== false) {
            return ['success' => true, 'message' => "User-Agent 已从黑名单移除"];
        } else {
            return ['success' => false, 'message' => '移除失败，请检查文件权限'];
        }
    }
    
    /**
     * 批量添加IP到黑名单
     */
    public function batchAddIPs($selected_items) {
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($selected_items as $index) {
            $ip = $_POST['ip_' . $index] ?? '';
            if (!empty($ip)) {
                $result = $this->addIPToBlacklist($ip);
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = $ip . ': ' . $result['message'];
                }
            }
        }
        
        $message = "批量操作完成：成功添加 {$success_count} 个IP";
        if ($error_count > 0) {
            $message .= "，失败 {$error_count} 个";
        }
        
        return [
            'success' => $success_count > 0,
            'message' => $message,
            'details' => $errors
        ];
    }
    
    /**
     * 批量添加UA到黑名单
     */
    public function batchAddUAs($selected_items) {
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($selected_items as $index) {
            $ua = $_POST['ua_' . $index] ?? '';
            if (!empty($ua)) {
                $result = $this->addUAToBlacklist($ua);
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = 'UA: ' . $result['message'];
                }
            }
        }
        
        $message = "批量操作完成：成功添加 {$success_count} 个UA";
        if ($error_count > 0) {
            $message .= "，失败 {$error_count} 个";
        }
        
        return [
            'success' => $success_count > 0,
            'message' => $message,
            'details' => $errors
        ];
    }
    
    /**
     * 批量同时添加IP和UA到黑名单
     */
    public function batchAddBoth($items) {
        $success_count = 0;
        $error_count = 0;
        $errors = [];
        
        foreach ($items as $item) {
            $ip = $item['ip'] ?? '';
            $ua = $item['ua'] ?? '';
            
            if (!empty($ip) && !empty($ua)) {
                $result = $this->addBothToBlacklist($ip, $ua);
                if ($result['success']) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = $ip . ': ' . $result['message'];
                }
            }
        }
        
        $message = "批量操作完成：成功添加 {$success_count} 对IP+UA";
        if ($error_count > 0) {
            $message .= "，失败 {$error_count} 对";
        }
        
        return [
            'success' => $success_count > 0,
            'message' => $message,
            'details' => $errors
        ];
    }
}
