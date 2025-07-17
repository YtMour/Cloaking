<?php
namespace Cloak\API;

require_once dirname(__DIR__) . '/Cloak_Core/Autoloader.php';

use Cloak\Core\Config;
use Cloak\Core\Logger;
use Cloak\Core\BlacklistChecker;

/**
 * 批量处理器
 * 负责批量操作黑名单和其他批量任务
 */
class BatchProcessor {
    private $config;
    private $logger;
    private $blacklistChecker;
    
    public function __construct() {
        $this->config = Config::getInstance();
        $this->logger = new Logger();
        $this->blacklistChecker = new BlacklistChecker();
    }
    
    /**
     * 添加IP到黑名单
     */
    public function addIPToBlacklist($ip) {
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => '❌ 无效的IP地址'];
        }
        
        if ($this->blacklistChecker->checkIP($ip)) {
            return ['success' => false, 'message' => '⚠️ IP已在黑名单中'];
        }
        
        if ($this->blacklistChecker->addIPToBlacklist($ip)) {
            $this->logger->logInfo("IP添加到黑名单: $ip");
            return ['success' => true, 'message' => "✅ IP $ip 已添加到黑名单"];
        } else {
            return ['success' => false, 'message' => '❌ 添加失败，请检查文件权限'];
        }
    }
    
    /**
     * 添加UA到黑名单
     */
    public function addUAToBlacklist($ua, $ip = null) {
        if (empty($ua)) {
            return ['success' => false, 'message' => '❌ User-Agent不能为空'];
        }
        
        if ($this->blacklistChecker->checkUA($ua)) {
            return ['success' => false, 'message' => '⚠️ User-Agent已在黑名单中'];
        }
        
        if ($this->blacklistChecker->addUAToBlacklist($ua, $ip)) {
            $this->logger->logInfo("UA添加到黑名单: $ua" . ($ip ? " [IP: $ip]" : ""));
            return ['success' => true, 'message' => "✅ User-Agent已添加到黑名单"];
        } else {
            return ['success' => false, 'message' => '❌ 添加失败，请检查文件权限'];
        }
    }
    
    /**
     * 同时添加IP和UA到黑名单
     */
    public function addBothToBlacklist($ip, $ua) {
        if (empty($ip) || empty($ua)) {
            return ['success' => false, 'message' => '❌ IP和User-Agent都不能为空'];
        }
        
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => '❌ 无效的IP地址'];
        }
        
        $results = [];
        
        // 添加到IP黑名单
        if (!$this->blacklistChecker->checkIP($ip)) {
            if ($this->blacklistChecker->addIPToBlacklist($ip)) {
                $results[] = "IP $ip";
            }
        }
        
        // 添加到UA黑名单（混合格式）
        if (!$this->blacklistChecker->checkUA($ua)) {
            if ($this->blacklistChecker->addUAToBlacklist($ua, $ip)) {
                $results[] = "UA (含IP)";
            }
        }
        
        if (!empty($results)) {
            $this->logger->logInfo("同时添加到黑名单 - IP: $ip, UA: $ua");
            return ['success' => true, 'message' => "✅ 已添加: " . implode(', ', $results)];
        } else {
            return ['success' => false, 'message' => '⚠️ 项目已在黑名单中或添加失败'];
        }
    }
    
    /**
     * 批量添加IP到黑名单
     */
    public function batchAddIPs($selectedItems) {
        if (empty($selectedItems)) {
            return ['success' => false, 'message' => '❌ 未选择任何项目'];
        }
        
        $successCount = 0;
        $totalCount = count($selectedItems);
        $errors = [];
        
        foreach ($selectedItems as $index) {
            $ip = $_POST['ip_' . $index] ?? '';
            
            if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                if (!$this->blacklistChecker->checkIP($ip)) {
                    if ($this->blacklistChecker->addIPToBlacklist($ip)) {
                        $successCount++;
                    } else {
                        $errors[] = "IP $ip 添加失败";
                    }
                }
            } else {
                $errors[] = "无效IP: $ip";
            }
        }
        
        $this->logger->logInfo("批量添加IP到黑名单: 成功 $successCount/$totalCount");
        
        if ($successCount > 0) {
            $message = "✅ 成功添加 $successCount 个IP到黑名单";
            if (!empty($errors)) {
                $message .= "，" . count($errors) . " 个失败";
            }
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => '❌ 批量添加失败: ' . implode(', ', $errors)];
        }
    }
    
    /**
     * 批量添加UA到黑名单
     */
    public function batchAddUAs($selectedItems) {
        if (empty($selectedItems)) {
            return ['success' => false, 'message' => '❌ 未选择任何项目'];
        }
        
        $successCount = 0;
        $totalCount = count($selectedItems);
        $errors = [];
        
        foreach ($selectedItems as $index) {
            $ua = $_POST['ua_' . $index] ?? '';
            
            if (!empty($ua)) {
                if (!$this->blacklistChecker->checkUA($ua)) {
                    if ($this->blacklistChecker->addUAToBlacklist($ua)) {
                        $successCount++;
                    } else {
                        $errors[] = "UA添加失败";
                    }
                }
            } else {
                $errors[] = "空UA";
            }
        }
        
        $this->logger->logInfo("批量添加UA到黑名单: 成功 $successCount/$totalCount");
        
        if ($successCount > 0) {
            $message = "✅ 成功添加 $successCount 个User-Agent到黑名单";
            if (!empty($errors)) {
                $message .= "，" . count($errors) . " 个失败";
            }
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => '❌ 批量添加失败'];
        }
    }
    
    /**
     * 批量添加IP和UA到黑名单
     */
    public function batchAddBoth($items) {
        if (empty($items)) {
            return ['success' => false, 'message' => '❌ 未选择任何项目'];
        }
        
        $successCount = 0;
        $totalCount = count($items);
        $errors = [];
        
        foreach ($items as $item) {
            $ip = $item['ip'] ?? '';
            $ua = $item['ua'] ?? '';
            
            if (!empty($ip) && !empty($ua) && filter_var($ip, FILTER_VALIDATE_IP)) {
                $added = false;
                
                // 添加IP
                if (!$this->blacklistChecker->checkIP($ip)) {
                    if ($this->blacklistChecker->addIPToBlacklist($ip)) {
                        $added = true;
                    }
                }
                
                // 添加UA（混合格式）
                if (!$this->blacklistChecker->checkUA($ua)) {
                    if ($this->blacklistChecker->addUAToBlacklist($ua, $ip)) {
                        $added = true;
                    }
                }
                
                if ($added) {
                    $successCount++;
                }
            } else {
                $errors[] = "无效数据: IP=$ip";
            }
        }
        
        $this->logger->logInfo("批量添加IP+UA到黑名单: 成功 $successCount/$totalCount");
        
        if ($successCount > 0) {
            $message = "✅ 成功处理 $successCount 个项目";
            if (!empty($errors)) {
                $message .= "，" . count($errors) . " 个失败";
            }
            return ['success' => true, 'message' => $message];
        } else {
            return ['success' => false, 'message' => '❌ 批量添加失败'];
        }
    }
    
    /**
     * 从黑名单移除IP
     */
    public function removeIPFromBlacklist($ip) {
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['success' => false, 'message' => '❌ 无效的IP地址'];
        }
        
        if (!$this->blacklistChecker->checkIP($ip)) {
            return ['success' => false, 'message' => '⚠️ IP不在黑名单中'];
        }
        
        if ($this->blacklistChecker->removeIPFromBlacklist($ip)) {
            $this->logger->logInfo("IP从黑名单移除: $ip");
            return ['success' => true, 'message' => "✅ IP $ip 已从黑名单移除"];
        } else {
            return ['success' => false, 'message' => '❌ 移除失败，请检查文件权限'];
        }
    }
    
    /**
     * 从黑名单移除UA
     */
    public function removeUAFromBlacklist($ua) {
        if (empty($ua)) {
            return ['success' => false, 'message' => '❌ User-Agent不能为空'];
        }
        
        if (!$this->blacklistChecker->checkUA($ua)) {
            return ['success' => false, 'message' => '⚠️ User-Agent不在黑名单中'];
        }
        
        if ($this->blacklistChecker->removeUAFromBlacklist($ua)) {
            $this->logger->logInfo("UA从黑名单移除: $ua");
            return ['success' => true, 'message' => "✅ User-Agent已从黑名单移除"];
        } else {
            return ['success' => false, 'message' => '❌ 移除失败，请检查文件权限'];
        }
    }
    
    /**
     * 检查IP是否在黑名单中
     */
    public function isIPInBlacklist($ip) {
        return $this->blacklistChecker->checkIP($ip);
    }
    
    /**
     * 检查UA是否在黑名单中
     */
    public function isUAInBlacklist($ua) {
        return $this->blacklistChecker->checkUA($ua);
    }
    
    /**
     * 批量导入IP黑名单
     */
    public function batchImportIPs($ips) {
        $successCount = 0;
        $errors = [];
        
        foreach ($ips as $ip) {
            $ip = trim($ip);
            if (!empty($ip) && filter_var($ip, FILTER_VALIDATE_IP)) {
                if (!$this->blacklistChecker->checkIP($ip)) {
                    if ($this->blacklistChecker->addIPToBlacklist($ip)) {
                        $successCount++;
                    } else {
                        $errors[] = $ip;
                    }
                }
            } else {
                $errors[] = $ip;
            }
        }
        
        $this->logger->logInfo("批量导入IP: 成功 $successCount 个，失败 " . count($errors) . " 个");
        
        return [
            'success' => $successCount > 0,
            'message' => "导入完成：成功 $successCount 个，失败 " . count($errors) . " 个",
            'success_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * 批量导入UA黑名单
     */
    public function batchImportUAs($uas) {
        $successCount = 0;
        $errors = [];
        
        foreach ($uas as $ua) {
            $ua = trim($ua);
            if (!empty($ua)) {
                if (!$this->blacklistChecker->checkUA($ua)) {
                    if ($this->blacklistChecker->addUAToBlacklist($ua)) {
                        $successCount++;
                    } else {
                        $errors[] = substr($ua, 0, 50) . '...';
                    }
                }
            }
        }
        
        $this->logger->logInfo("批量导入UA: 成功 $successCount 个，失败 " . count($errors) . " 个");
        
        return [
            'success' => $successCount > 0,
            'message' => "导入完成：成功 $successCount 个，失败 " . count($errors) . " 个",
            'success_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors
        ];
    }
}
?>
