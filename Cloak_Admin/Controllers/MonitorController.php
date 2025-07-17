<?php

require_once __DIR__ . '/../Classes/LogManager.php';
require_once __DIR__ . '/../Classes/BlacklistOperations.php';

/**
 * 监控控制器 - 完全按照原始 admin_monitor.php 逻辑
 */
class MonitorController {
    private $config;
    private $logManager;
    private $blacklistOps;

    public function __construct($config) {
        $this->config = $config;
        $this->logManager = new LogManager($config);
        $this->blacklistOps = new BlacklistOperations($config);
    }

    public function index() {
        $msg = '';
        $msg_type = 'success';

        // 处理黑名单操作 - 完全按照原始逻辑
        if (isset($_POST['add_to_blacklist'])) {
            $ip = trim($_POST['ip'] ?? '');
            $ua = trim($_POST['ua'] ?? '');
            $type = $_POST['type'] ?? '';

            if ($type === 'ip' && !empty($ip)) {
                $result = $this->blacklistOps->addIPToBlacklist($ip);
                $msg = $result['message'];
                $msg_type = $result['success'] ? 'success' : 'error';
            } elseif ($type === 'ua' && !empty($ua)) {
                $result = $this->blacklistOps->addUAToBlacklist($ua);
                $msg = $result['message'];
                $msg_type = $result['success'] ? 'success' : 'error';
            } elseif ($type === 'both' && !empty($ip) && !empty($ua)) {
                $result = $this->blacklistOps->addBothToBlacklist($ip, $ua);
                $msg = $result['message'];
                $msg_type = $result['success'] ? 'success' : 'error';
            } else {
                $msg = "❌ 参数错误";
                $msg_type = 'error';
            }
        }

        // 批量添加到黑名单 - 完全按照原始逻辑
        if (isset($_POST['batch_add_to_blacklist'])) {
            $selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];
            $batch_type = $_POST['batch_add_to_blacklist'] ?? '';

            if (!empty($selected_items)) {
                if ($batch_type === 'ip') {
                    $result = $this->blacklistOps->batchAddIPs($selected_items);
                    $msg = $result['message'];
                    $msg_type = $result['success'] ? 'success' : 'error';
                } elseif ($batch_type === 'ua') {
                    $result = $this->blacklistOps->batchAddUAs($selected_items);
                    $msg = $result['message'];
                    $msg_type = $result['success'] ? 'success' : 'error';
                } elseif ($batch_type === 'both') {
                    $items = [];
                    foreach ($selected_items as $index) {
                        if (isset($_POST['ip_' . $index]) && isset($_POST['ua_' . $index])) {
                            $items[] = [
                                'ip' => $_POST['ip_' . $index],
                                'ua' => $_POST['ua_' . $index]
                            ];
                        }
                    }
                    $result = $this->blacklistOps->batchAddBoth($items);
                    $msg = $result['message'];
                    $msg_type = $result['success'] ? 'success' : 'error';
                } else {
                    $msg = "❌ 批量操作类型错误";
                    $msg_type = 'error';
                }
            } else {
                $msg = "⚠️ 未选择任何项目";
                $msg_type = 'error';
            }
        }

        // 从黑名单移除 - 完全按照原始逻辑
        if (isset($_POST['remove_from_blacklist'])) {
            $ip = trim($_POST['ip'] ?? '');
            $ua = trim($_POST['ua'] ?? '');
            $type = $_POST['type'] ?? '';

            if ($type === 'ip' && !empty($ip)) {
                $result = $this->blacklistOps->removeIPFromBlacklist($ip);
                $msg = $result['message'];
                $msg_type = $result['success'] ? 'success' : 'error';
            } elseif ($type === 'ua' && !empty($ua)) {
                $result = $this->blacklistOps->removeUAFromBlacklist($ua);
                $msg = $result['message'];
                $msg_type = $result['success'] ? 'success' : 'error';
            } else {
                $msg = "❌ 参数错误";
                $msg_type = 'error';
            }
        }

        // 处理清空日志
        if (isset($_GET['clear_log'])) {
            $result = $this->logManager->clearLog();
            $msg = $result['message'];
            $msg_type = $result['success'] ? 'success' : 'error';
        }

        // 获取过滤参数 - 完全按照原始逻辑
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
        $filters = [
            'ip' => $_GET['filter_ip'] ?? '',
            'ua' => $_GET['filter_ua'] ?? '',
            'action' => $_GET['filter_action'] ?? '',
            'date' => $_GET['filter_date'] ?? ''
        ];

        // 获取日志数据 - 完全按照原始逻辑
        $log_data = $this->logManager->getLogData($page, $per_page, $filters);
        $logs = $log_data['logs'];
        $total_logs = $log_data['total'];
        $total_pages = $log_data['total_pages'];

        // 为每个日志添加黑名单状态 - 完全按照原始逻辑
        foreach ($logs as &$log) {
            $log['ip_in_blacklist'] = $this->blacklistOps->isIPInBlacklist($log['ip']);
            $log['ua_in_blacklist'] = $this->blacklistOps->isUAInBlacklist($log['ua']);
        }

        // 获取统计信息
        $log_stats = $this->logManager->getLogStats();
        $system_info = $this->logManager->getSystemInfo();

        return [
            'logs' => $logs,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_logs' => $total_logs,
                'per_page' => $per_page,
                'has_prev' => $page > 1,
                'has_next' => $page < $total_pages,
                'prev_page' => $page - 1,
                'next_page' => $page + 1
            ],
            'stats' => $log_stats,
            'system_info' => $system_info,
            'message' => $msg,
            'message_type' => $msg_type
        ];
    }
}