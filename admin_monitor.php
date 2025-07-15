<?php
/**
 * 系统监控模块 - 优化版
 * 查看访问日志、系统状态和性能统计
 * 支持分页、过滤、多选批量操作等功能
 */

require_once 'admin_core.php';
require_once 'log_manager.php';
require_once 'blacklist_operations.php';

// 认证检查
$auth_result = checkAuth($config['password']);
if ($auth_result !== true) {
    if (is_array($auth_result)) {
        showLoginPage($auth_result['error']);
    } else {
        showLoginPage();
    }
}

// 初始化模块
$logManager = new LogManager($config);
$blacklistOps = new BlacklistOperations($config);

$msg = '';
$msg_type = 'success';

// 处理黑名单操作
// 单个添加到黑名单
if (isset($_POST['add_to_blacklist'])) {
    $ip = trim($_POST['ip'] ?? '');
    $ua = trim($_POST['ua'] ?? '');
    $type = $_POST['type'] ?? '';

    if ($type === 'ip' && !empty($ip)) {
        $result = $blacklistOps->addIPToBlacklist($ip);
        $msg = $result['message'];
        $msg_type = $result['success'] ? 'success' : 'error';
    } elseif ($type === 'ua' && !empty($ua)) {
        $result = $blacklistOps->addUAToBlacklist($ua);
        $msg = $result['message'];
        $msg_type = $result['success'] ? 'success' : 'error';
    } elseif ($type === 'both' && !empty($ip) && !empty($ua)) {
        $result = $blacklistOps->addBothToBlacklist($ip, $ua);
        $msg = $result['message'];
        $msg_type = $result['success'] ? 'success' : 'error';
    } else {
        $msg = "❌ 参数错误";
        $msg_type = 'error';
    }
}

// 批量添加到黑名单
if (isset($_POST['batch_add_to_blacklist'])) {
    $selected_items = isset($_POST['selected_items']) ? $_POST['selected_items'] : [];
    $batch_type = $_POST['batch_type'] ?? '';

    if (!empty($selected_items)) {
        if ($batch_type === 'ip') {
            $result = $blacklistOps->batchAddIPs($selected_items);
            $msg = $result['message'];
            $msg_type = $result['success'] ? 'success' : 'error';
        } elseif ($batch_type === 'ua') {
            $result = $blacklistOps->batchAddUAs($selected_items);
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
            $result = $blacklistOps->batchAddBoth($items);
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

// 从黑名单移除
if (isset($_POST['remove_from_blacklist'])) {
    $ip = trim($_POST['ip'] ?? '');
    $ua = trim($_POST['ua'] ?? '');
    $type = $_POST['type'] ?? '';

    if ($type === 'ip' && !empty($ip)) {
        $result = $blacklistOps->removeIPFromBlacklist($ip);
        $msg = $result['message'];
        $msg_type = $result['success'] ? 'success' : 'error';
    } elseif ($type === 'ua' && !empty($ua)) {
        $result = $blacklistOps->removeUAFromBlacklist($ua);
        $msg = $result['message'];
        $msg_type = $result['success'] ? 'success' : 'error';
    } else {
        $msg = "❌ 参数错误";
        $msg_type = 'error';
    }
}

// 清空日志
if (isset($_GET['clear_log'])) {
    if ($logManager->clearLog()) {
        $msg = "✅ 访问日志已清空";
    } else {
        $msg = "❌ 清空日志失败";
        $msg_type = 'error';
    }
}

// 获取系统信息
function getSystemInfo($config) {
    $info = [];

    // 文件状态
    $files = [
        'UA 黑名单' => $config['ua_file'],
        'IP 黑名单' => $config['ip_file'],
        '跳转地址' => $config['landing_file'],
        '访问日志' => $config['log_file'],
        'API 配置' => $config['api_config_file']
    ];

    foreach ($files as $name => $file) {
        $info['files'][$name] = [
            'exists' => file_exists($file),
            'size' => file_exists($file) ? filesize($file) : 0,
            'modified' => file_exists($file) ? date('Y-m-d H:i:s', filemtime($file)) : '-'
        ];
    }

    // 黑名单统计
    $stats = getBlacklistStats($config);
    $info['blacklist'] = $stats;

    return $info;
}

// 处理分页和过滤参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = isset($_GET['per_page']) ? intval($_GET['per_page']) : 50;
$filters = [
    'ip' => $_GET['filter_ip'] ?? '',
    'ua' => $_GET['filter_ua'] ?? '',
    'action' => $_GET['filter_action'] ?? '',
    'date' => $_GET['filter_date'] ?? ''
];

// 获取日志数据
$log_data = $logManager->getLogData($page, $per_page, $filters);
$logs = $log_data['logs'];
$total_logs = $log_data['total'];
$total_pages = $log_data['total_pages'];

// 获取统计信息
$log_stats = $logManager->getLogStats();
$system_info = getSystemInfo($config);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <title>系统监控</title>
    <?php echo getCommonStyles(); ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        
        .stat-card {
            text-align: center;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid;
        }
        
        .log-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 13px;
        }
        
        .log-table th, .log-table td {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .log-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .log-table tr:nth-child(even) {
            background: #f8f9fa;
        }

        /* 被拦截的行 - 红色背景 */
        .log-table tr.blocked {
            background: rgba(231, 76, 60, 0.1) !important;
            border-left: 4px solid #e74c3c;
        }

        /* 正常通过的行 - 绿色背景 */
        .log-table tr.passed {
            background: rgba(39, 174, 96, 0.1) !important;
            border-left: 4px solid #27ae60;
        }

        .action-blocked {
            color: #e74c3c;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            background: rgba(231, 76, 60, 0.2);
        }

        .action-redirect {
            color: #27ae60;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 4px;
            background: rgba(39, 174, 96, 0.2);
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 5px;
        }
        
        .btn-danger {
            background: #e74c3c;
            color: white;
        }
        
        .btn-primary {
            background: #3498db;
            color: white;
        }
        
        .system-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .system-table th, .system-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .system-table th {
            background: #f8f9fa;
        }
        
        .status-ok { color: #27ae60; }
        .status-error { color: #e74c3c; }

        /* 黑名单操作按钮样式 */
        .blacklist-btn {
            padding: 4px 8px;
            border: none;
            border-radius: 4px;
            font-size: 11px;
            cursor: pointer;
            margin: 2px;
            text-decoration: none;
            display: inline-block;
            transition: all 0.2s ease;
        }

        .blacklist-btn-add {
            background: #e74c3c;
            color: white;
        }

        .blacklist-btn-add:hover {
            background: #c0392b;
            transform: translateY(-1px);
        }

        .blacklist-btn-remove {
            background: #27ae60;
            color: white;
        }

        .blacklist-btn-remove:hover {
            background: #229954;
            transform: translateY(-1px);
        }

        .blacklist-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }

        .blacklist-status {
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 3px;
            margin: 1px;
            display: inline-block;
        }

        .blacklist-status-in {
            background: rgba(231, 76, 60, 0.2);
            color: #e74c3c;
        }

        .blacklist-status-out {
            background: rgba(39, 174, 96, 0.2);
            color: #27ae60;
        }

        .action-column {
            min-width: 120px;
            text-align: center;
        }

        /* 多选和批量操作样式 */
        .batch-controls {
            background: rgba(52,152,219,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(52,152,219,0.3);
        }

        .batch-controls h4 {
            margin: 0 0 10px 0;
            color: #2c3e50;
        }

        .batch-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .batch-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 5px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .batch-btn-danger {
            background: #e74c3c;
            color: white;
        }

        .batch-btn-warning {
            background: #f39c12;
            color: white;
        }

        .batch-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .batch-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }

        .select-all-checkbox {
            margin-right: 8px;
        }

        /* 过滤器样式 */
        .filters {
            background: rgba(155,89,182,0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid rgba(155,89,182,0.3);
        }

        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
        }

        .filter-group input, .filter-group select {
            padding: 6px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
        }

        /* 分页样式 */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 20px 0;
        }

        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #333;
        }

        .pagination a:hover {
            background: #f8f9fa;
        }

        .pagination .current {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }

        .pagination .disabled {
            color: #999;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="header">
        <h2>📊 系统监控</h2>
        <a href="?logout=1" class="logout-btn">🚪 退出后台</a>
        <?php echo getNavMenu('admin_monitor.php'); ?>
    </div>

    <?php showMessage($msg, $msg_type); ?>

    <!-- 访问统计 -->
    <div class="card">
        <h3>📈 访问统计</h3>
        <div class="stats-grid">
            <div class="stat-card" style="border-color: #3498db; background: rgba(52,152,219,0.1);">
                <div style="font-size: 28px; font-weight: bold; color: #3498db;"><?php echo $log_stats['total']; ?></div>
                <div style="font-size: 14px; color: #666;">总访问次数</div>
            </div>
            <div class="stat-card" style="border-color: #e74c3c; background: rgba(231,76,60,0.1);">
                <div style="font-size: 28px; font-weight: bold; color: #e74c3c;">🚫 <?php echo $log_stats['blocked']; ?></div>
                <div style="font-size: 14px; color: #666;">被拦截次数</div>
            </div>
            <div class="stat-card" style="border-color: #27ae60; background: rgba(39,174,96,0.1);">
                <div style="font-size: 28px; font-weight: bold; color: #27ae60;">✅ <?php echo $log_stats['redirected']; ?></div>
                <div style="font-size: 14px; color: #666;">正常通过次数</div>
            </div>
            <div class="stat-card" style="border-color: #f39c12; background: rgba(243,156,18,0.1);">
                <div style="font-size: 28px; font-weight: bold; color: #f39c12;"><?php echo $log_stats['today']; ?></div>
                <div style="font-size: 14px; color: #666;">今日访问次数</div>
            </div>
            <div class="stat-card" style="border-color: #9b59b6; background: rgba(155,89,182,0.1);">
                <div style="font-size: 28px; font-weight: bold; color: #9b59b6;"><?php echo $log_stats['unique_ips']; ?></div>
                <div style="font-size: 14px; color: #666;">独立访客数</div>
            </div>
        </div>
    </div>




    <!-- 系统状态 -->
    <div class="card">
        <h3>⚙️ 系统状态</h3>
        <table class="system-table">
            <thead>
                <tr>
                    <th>文件</th>
                    <th>状态</th>
                    <th>大小</th>
                    <th>最后修改</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($system_info['files'] as $name => $file): ?>
                <tr>
                    <td><?php echo htmlspecialchars($name); ?></td>
                    <td class="<?php echo $file['exists'] ? 'status-ok' : 'status-error'; ?>">
                        <?php echo $file['exists'] ? '✅ 存在' : '❌ 不存在'; ?>
                    </td>
                    <td><?php echo $file['exists'] ? number_format($file['size']) . ' 字节' : '-'; ?></td>
                    <td><?php echo htmlspecialchars($file['modified']); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 访问日志 -->
    <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>📋 访问日志 (共 <?php echo $total_logs; ?> 条记录)</h3>
            <div>
                <button onclick="location.reload()" class="btn btn-primary">🔄 刷新</button>
                <?php if (!empty($logs)): ?>
                    <button onclick="if(confirm('确定要清空所有访问日志吗？')){location.href='?clear_log=1';}"
                            class="btn btn-danger">🗑️ 清空日志</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- 过滤器 -->
        <div class="filters">
            <h4 style="margin: 0 0 10px 0; color: #2c3e50;">🔍 日志过滤</h4>
            <form method="get" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>IP 地址</label>
                        <input type="text" name="filter_ip" value="<?php echo htmlspecialchars($filters['ip']); ?>" placeholder="输入IP">
                    </div>
                    <div class="filter-group">
                        <label>User Agent</label>
                        <input type="text" name="filter_ua" value="<?php echo htmlspecialchars($filters['ua']); ?>" placeholder="输入UA关键词">
                    </div>
                    <div class="filter-group">
                        <label>处理结果</label>
                        <select name="filter_action">
                            <option value="" <?php echo $filters['action'] === '' ? 'selected' : ''; ?>>全部</option>
                            <option value="blocked" <?php echo $filters['action'] === 'blocked' ? 'selected' : ''; ?>>仅拦截</option>
                            <option value="passed" <?php echo $filters['action'] === 'passed' ? 'selected' : ''; ?>>仅通过</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>日期 (YYYY-MM-DD)</label>
                        <input type="text" name="filter_date" value="<?php echo htmlspecialchars($filters['date']); ?>" placeholder="YYYY-MM-DD">
                    </div>
                    <div class="filter-group">
                        <label>每页显示</label>
                        <select name="per_page">
                            <option value="20" <?php echo $per_page === 20 ? 'selected' : ''; ?>>20条</option>
                            <option value="50" <?php echo $per_page === 50 ? 'selected' : ''; ?>>50条</option>
                            <option value="100" <?php echo $per_page === 100 ? 'selected' : ''; ?>>100条</option>
                            <option value="200" <?php echo $per_page === 200 ? 'selected' : ''; ?>>200条</option>
                        </select>
                    </div>
                    <div class="filter-group" style="align-self: flex-end;">
                        <button type="submit" class="btn btn-primary" style="margin: 0;">应用过滤</button>
                        <a href="?page=1" class="btn" style="margin: 0; background: #eee;">重置</a>
                    </div>
                </div>
            </form>
        </div>

        <!-- 颜色图例 -->
        <div style="margin-bottom: 20px; padding: 15px; background: rgba(52,152,219,0.1); border-radius: 8px;">
            <h4 style="margin: 0 0 10px 0; color: #2c3e50;">📊 日志说明</h4>
            <div style="display: flex; gap: 30px; flex-wrap: wrap; margin-bottom: 15px;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(231, 76, 60, 0.3); border-left: 4px solid #e74c3c; border-radius: 3px;"></div>
                    <span style="font-size: 14px;">🚫 <strong>被拦截</strong> - 显示假页面（机器人、测试模式）</span>
                </div>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="width: 20px; height: 20px; background: rgba(39, 174, 96, 0.3); border-left: 4px solid #27ae60; border-radius: 3px;"></div>
                    <span style="font-size: 14px;">✅ <strong>正常通过</strong> - 跳转到目标地址</span>
                </div>
            </div>
            <div style="font-size: 13px; color: #666; border-top: 1px solid rgba(52,152,219,0.2); padding-top: 10px;">
                <strong>💡 访问来源说明：</strong>
                <span style="font-style: italic;">"直接访问"</span> 表示用户直接输入网址、使用书签，或者访问者未发送来源信息（常见于机器人访问）
            </div>
        </div>

        <?php if (!empty($logs)): ?>
            <!-- 批量操作控制区 -->
            <form method="post" id="batch-form">
                <div class="batch-controls">
                    <h4>🔄 批量操作</h4>
                    <div style="font-size: 13px; color: #666; margin-bottom: 10px;">
                        选择下方日志条目，然后点击相应按钮进行批量操作
                    </div>
                    <div class="batch-buttons">
                        <button type="submit" name="batch_add_to_blacklist" value="ip" class="batch-btn batch-btn-danger"
                                onclick="return confirmBatchAction('IP')">
                            🚫 批量拉黑选中IP
                        </button>
                        <button type="submit" name="batch_add_to_blacklist" value="ua" class="batch-btn batch-btn-danger"
                                onclick="return confirmBatchAction('UA')">
                            🚫 批量拉黑选中UA
                        </button>
                        <button type="submit" name="batch_add_to_blacklist" value="both" class="batch-btn batch-btn-warning"
                                onclick="return confirmBatchAction('IP和UA')">
                            ⚡ 同时拉黑选中IP+UA
                        </button>
                    </div>
                    <input type="hidden" name="batch_type" id="batch_type" value="">
                </div>

                <div style="overflow-x: auto;">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" class="select-all-checkbox" id="select-all"
                                           onclick="toggleSelectAll(this)">
                                </th>
                                <th>时间</th>
                                <th>IP 地址</th>
                                <th>User Agent</th>
                                <th>处理结果</th>
                                <th>访问来源</th>
                                <th>黑名单操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $index => $log):
                                // 判断是否被拦截
                                $is_blocked = (strpos($log['action'], '假页面') !== false ||
                                             strpos($log['action'], 'BLOCKED') !== false ||
                                             strpos($log['action'], '机器人检测') !== false ||
                                             strpos($log['action'], '测试模式') !== false);
                                $row_class = $is_blocked ? 'blocked' : 'passed';
                                $action_class = $is_blocked ? 'action-blocked' : 'action-redirect';

                                // 检查是否在黑名单中
                                $ip_in_blacklist = $blacklistOps->isIPInBlacklist($log['ip']);
                                $ua_in_blacklist = $blacklistOps->isUAInBlacklist($log['ua']);
                            ?>
                            <tr class="<?php echo $row_class; ?>">
                                <td>
                                    <input type="checkbox" name="selected_items[]" value="<?php echo $index; ?>" class="row-checkbox">
                                    <input type="hidden" name="ip_<?php echo $index; ?>" value="<?php echo htmlspecialchars($log['ip']); ?>">
                                    <input type="hidden" name="ua_<?php echo $index; ?>" value="<?php echo htmlspecialchars($log['ua']); ?>">
                                </td>
                                <td><?php echo htmlspecialchars($log['time']); ?></td>
                                <td style="font-family: monospace;"><?php echo htmlspecialchars($log['ip']); ?></td>
                                <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"
                                    title="<?php echo htmlspecialchars($log['ua']); ?>">
                                    <?php echo htmlspecialchars($log['ua']); ?>
                                </td>
                                <td class="<?php echo $action_class; ?>">
                                    <?php if ($is_blocked): ?>
                                        🚫 <?php echo htmlspecialchars($log['action']); ?>
                                    <?php else: ?>
                                        ✅ <?php echo htmlspecialchars($log['action']); ?>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; color: #666;">
                                    <?php
                                    $referer = $log['referer'];
                                    if ($referer === '-' || empty($referer)) {
                                        echo '<span style="color: #999; font-style: italic;">直接访问</span>';
                                    } else {
                                        // 简化显示长URL
                                        $display_referer = $referer;
                                        if (strlen($referer) > 50) {
                                            $parsed = parse_url($referer);
                                            $display_referer = ($parsed['host'] ?? '') . '...';
                                        }
                                        echo '<span title="' . htmlspecialchars($referer) . '">' . htmlspecialchars($display_referer) . '</span>';
                                    }
                                    ?>
                                </td>
                                <td class="action-column">
                                    <!-- IP 操作 -->
                                    <div style="margin-bottom: 5px;">
                                        <?php if ($ip_in_blacklist): ?>
                                            <span class="blacklist-status blacklist-status-in" title="此IP已在黑名单中">IP已拉黑</span>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="ip" value="<?php echo htmlspecialchars($log['ip']); ?>">
                                                <input type="hidden" name="type" value="ip">
                                                <button type="submit" name="remove_from_blacklist" class="blacklist-btn blacklist-btn-remove"
                                                        onclick="return confirm('确定要将此IP从黑名单移除吗？')">
                                                    ✅ 移除IP
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <?php
                                            // 检查是否是云服务器IP
                                            $is_cloud_ip = false;
                                            $cloud_ip_prefix = ['34.', '35.', '66.249.', '104.28.', '54.'];
                                            foreach ($cloud_ip_prefix as $prefix) {
                                                if (strpos($log['ip'], $prefix) === 0) {
                                                    $is_cloud_ip = true;
                                                    break;
                                                }
                                            }

                                            if ($is_cloud_ip):
                                            ?>
                                                <span class="blacklist-status blacklist-status-in" title="此IP属于云服务器IP前缀，系统自动拦截">云服务IP</span>
                                            <?php else: ?>
                                                <span class="blacklist-status blacklist-status-out">IP未拉黑</span>
                                            <?php endif; ?>

                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="ip" value="<?php echo htmlspecialchars($log['ip']); ?>">
                                                <input type="hidden" name="type" value="ip">
                                                <button type="submit" name="add_to_blacklist" class="blacklist-btn blacklist-btn-add"
                                                        onclick="return confirm('确定要将此IP添加到黑名单吗？')">
                                                    🚫 拉黑IP
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <!-- UA 操作 -->
                                    <div>
                                        <?php if ($ua_in_blacklist): ?>
                                            <span class="blacklist-status blacklist-status-in" title="此UA已在黑名单中">UA已拉黑</span>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="ua" value="<?php echo htmlspecialchars($log['ua']); ?>">
                                                <input type="hidden" name="type" value="ua">
                                                <button type="submit" name="remove_from_blacklist" class="blacklist-btn blacklist-btn-remove"
                                                        onclick="return confirm('确定要将此UA从黑名单移除吗？')">
                                                    ✅ 移除UA
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="blacklist-status blacklist-status-out">UA未拉黑</span>
                                            <form method="post" style="display: inline;">
                                                <input type="hidden" name="ua" value="<?php echo htmlspecialchars($log['ua']); ?>">
                                                <input type="hidden" name="type" value="ua">
                                                <button type="submit" name="add_to_blacklist" class="blacklist-btn blacklist-btn-add"
                                                        onclick="return confirm('确定要将此UA添加到黑名单吗？')">
                                                    🚫 拉黑UA
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <!-- 同时拉黑IP+UA按钮 -->
                                    <?php if (!$ip_in_blacklist && !$ua_in_blacklist): ?>
                                    <div style="margin-top: 5px;">
                                        <form method="post" style="display: inline;">
                                            <input type="hidden" name="ip" value="<?php echo htmlspecialchars($log['ip']); ?>">
                                            <input type="hidden" name="ua" value="<?php echo htmlspecialchars($log['ua']); ?>">
                                            <input type="hidden" name="type" value="both">
                                            <button type="submit" name="add_to_blacklist" class="blacklist-btn"
                                                    style="background: #f39c12; color: white;"
                                                    onclick="return confirm('确定要同时将此IP和UA添加到黑名单吗？')">
                                                ⚡ IP+UA
                                            </button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- 分页控件 -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php
                // 构建保留所有过滤参数的URL
                $url_params = [];
                foreach ($filters as $key => $value) {
                    if (!empty($value)) {
                        $url_params[] = "filter_{$key}=" . urlencode($value);
                    }
                }
                if ($per_page !== 50) {
                    $url_params[] = "per_page={$per_page}";
                }
                $url_base = '?' . implode('&', $url_params) . '&page=';

                // 上一页
                if ($page > 1) {
                    echo '<a href="' . $url_base . ($page - 1) . '">上一页</a>';
                } else {
                    echo '<span class="disabled">上一页</span>';
                }

                // 页码
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);

                if ($start_page > 1) {
                    echo '<a href="' . $url_base . '1">1</a>';
                    if ($start_page > 2) {
                        echo '<span>...</span>';
                    }
                }

                for ($i = $start_page; $i <= $end_page; $i++) {
                    if ($i == $page) {
                        echo '<span class="current">' . $i . '</span>';
                    } else {
                        echo '<a href="' . $url_base . $i . '">' . $i . '</a>';
                    }
                }

                if ($end_page < $total_pages) {
                    if ($end_page < $total_pages - 1) {
                        echo '<span>...</span>';
                    }
                    echo '<a href="' . $url_base . $total_pages . '">' . $total_pages . '</a>';
                }

                // 下一页
                if ($page < $total_pages) {
                    echo '<a href="' . $url_base . ($page + 1) . '">下一页</a>';
                } else {
                    echo '<span class="disabled">下一页</span>';
                }
                ?>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div style="text-align: center; padding: 60px; color: #6c757d;">
                <h4>📝 暂无访问日志</h4>
                <p>当有访问者访问网站时，日志会自动记录在这里</p>
            </div>
        <?php endif; ?>
    </div>

</div>

<script>
// 全选/取消全选功能
function toggleSelectAll(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    updateBatchButtons();
}

// 更新批量操作按钮状态
function updateBatchButtons() {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    const batchButtons = document.querySelectorAll('.batch-btn');

    batchButtons.forEach(button => {
        button.disabled = checkedBoxes.length === 0;
    });

    // 更新选中数量显示
    const batchControls = document.querySelector('.batch-controls h4');
    if (checkedBoxes.length > 0) {
        batchControls.textContent = `🔄 批量操作 (已选择 ${checkedBoxes.length} 项)`;
    } else {
        batchControls.textContent = '🔄 批量操作';
    }
}

// 确认批量操作
function confirmBatchAction(type) {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) {
        alert('请先选择要操作的日志条目');
        return false;
    }

    const count = checkedBoxes.length;
    const message = `确定要批量拉黑选中的 ${count} 个${type}吗？\n\n此操作将把选中的${type}添加到黑名单中。`;

    if (confirm(message)) {
        // 设置批量操作类型
        const batchType = event.target.value;
        document.getElementById('batch_type').value = batchType;

        // 收集选中项目的数据
        const selectedItems = [];
        checkedBoxes.forEach((checkbox, index) => {
            const value = checkbox.value;
            if (batchType === 'ip') {
                const ip = document.querySelector(`input[name="ip_${value}"]`).value;
                selectedItems.push(ip);
            } else if (batchType === 'ua') {
                const ua = document.querySelector(`input[name="ua_${value}"]`).value;
                selectedItems.push(ua);
            }
        });

        // 动态创建隐藏字段来传递选中的数据
        const form = document.getElementById('batch-form');

        // 清除之前的隐藏字段
        const existingFields = form.querySelectorAll('input[name="selected_items[]"]');
        existingFields.forEach(field => {
            if (field.type === 'hidden') {
                field.remove();
            }
        });

        // 添加新的隐藏字段
        selectedItems.forEach(item => {
            const hiddenField = document.createElement('input');
            hiddenField.type = 'hidden';
            hiddenField.name = 'selected_items[]';
            hiddenField.value = item;
            form.appendChild(hiddenField);
        });

        return true;
    }

    return false;
}

// 监听复选框变化
document.addEventListener('DOMContentLoaded', function() {
    // 为所有行复选框添加事件监听
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            updateBatchButtons();

            // 更新全选复选框状态
            const selectAllCheckbox = document.getElementById('select-all');
            const totalCheckboxes = rowCheckboxes.length;
            const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked').length;

            if (checkedCheckboxes === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedCheckboxes === totalCheckboxes) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
            }
        });
    });

    // 初始化按钮状态
    updateBatchButtons();
});

// 快捷键支持
document.addEventListener('keydown', function(e) {
    // Ctrl+A 全选
    if (e.ctrlKey && e.key === 'a' && e.target.tagName !== 'INPUT') {
        e.preventDefault();
        const selectAllCheckbox = document.getElementById('select-all');
        selectAllCheckbox.checked = true;
        toggleSelectAll(selectAllCheckbox);
    }

    // Escape 取消选择
    if (e.key === 'Escape') {
        const selectAllCheckbox = document.getElementById('select-all');
        selectAllCheckbox.checked = false;
        toggleSelectAll(selectAllCheckbox);
    }
});
</script>

</body>
</html>
