<?php
/**
 * 系统监控模块
 * 查看访问日志、系统状态和性能统计
 */

require_once 'admin_core.php';

// 认证检查
$auth_result = checkAuth($config['password']);
if ($auth_result !== true) {
    if (is_array($auth_result)) {
        showLoginPage($auth_result['error']);
    } else {
        showLoginPage();
    }
}

$msg = '';
$msg_type = 'success';

// 清空日志
if (isset($_GET['clear_log'])) {
    file_put_contents($config['log_file'], '');
    $msg = "✅ 访问日志已清空";
}

// 读取日志文件
function getLogData($log_file, $limit = null) {
    if (!file_exists($log_file)) {
        return [];
    }

    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $logs = [];

    // 倒序读取最新的日志
    $lines = array_reverse($lines);
    $count = 0;

    foreach ($lines as $line) {
        if ($limit && $count >= $limit) break;

        // 处理新格式：时间 | IP | UA | 动作 | 来源
        $parts = explode(' | ', $line);
        if (count($parts) >= 4) {
            $logs[] = [
                'time' => $parts[0] ?? '',
                'ip' => $parts[1] ?? '',
                'ua' => $parts[2] ?? '',
                'action' => $parts[3] ?? '',
                'referer' => $parts[4] ?? '-'
            ];
            $count++;
        } else {
            // 处理旧格式：时间 | IP: xxx | UA: xxx | Referer: xxx
            if (preg_match('/^(.+?) \| IP: (.+?) \| UA: (.+?) \| Referer: (.+)$/', $line, $matches)) {
                $logs[] = [
                    'time' => $matches[1],
                    'ip' => $matches[2],
                    'ua' => $matches[3],
                    'action' => '未知动作 (旧格式)',
                    'referer' => $matches[4]
                ];
                $count++;
            }
        }
    }

    return $logs;
}

// 获取日志统计
function getLogStats($log_file) {
    if (!file_exists($log_file)) {
        return [
            'total' => 0,
            'blocked' => 0,
            'redirected' => 0,
            'today' => 0,
            'unique_ips' => 0
        ];
    }
    
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $total = count($lines);
    $blocked = 0;
    $redirected = 0;
    $today = 0;
    $ips = [];
    $today_date = date('Y-m-d');
    
    foreach ($lines as $line) {
        $parts = explode(' | ', $line);
        if (count($parts) >= 4) {
            $time = $parts[0];
            $ip = $parts[1];
            $action = $parts[3];

            // 统计IP
            $ips[$ip] = true;

            // 统计今日访问
            if (strpos($time, $today_date) === 0) {
                $today++;
            }

            // 统计动作
            if (strpos($action, '假页面') !== false || strpos($action, 'BLOCKED') !== false) {
                $blocked++;
            } else {
                $redirected++;
            }
        } else {
            // 处理旧格式
            if (preg_match('/^(.+?) \| IP: (.+?) \| UA: (.+?) \| Referer: (.+)$/', $line, $matches)) {
                $time = $matches[1];
                $ip = $matches[2];

                // 统计IP
                $ips[$ip] = true;

                // 统计今日访问
                if (strpos($time, $today_date) === 0) {
                    $today++;
                }

                // 旧格式无法确定动作，暂时归为重定向
                $redirected++;
            }
        }
    }
    
    return [
        'total' => $total,
        'blocked' => $blocked,
        'redirected' => $redirected,
        'today' => $today,
        'unique_ips' => count($ips)
    ];
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

$logs = getLogData($config['log_file']); // 显示全部日志
$log_stats = getLogStats($config['log_file']);
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
            <h3>📋 访问日志 (全部记录)</h3>
            <div>
                <button onclick="location.reload()" class="btn btn-primary">🔄 刷新</button>
                <?php if (!empty($logs)): ?>
                    <button onclick="if(confirm('确定要清空所有访问日志吗？')){location.href='?clear_log=1';}"
                            class="btn btn-danger">🗑️ 清空日志</button>
                <?php endif; ?>
            </div>
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
            <div style="overflow-x: auto;">
                <table class="log-table">
                    <thead>
                        <tr>
                            <th>时间</th>
                            <th>IP 地址</th>
                            <th>User Agent</th>
                            <th>处理结果</th>
                            <th>访问来源</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log):
                            // 判断是否被拦截
                            $is_blocked = (strpos($log['action'], '假页面') !== false ||
                                         strpos($log['action'], 'BLOCKED') !== false ||
                                         strpos($log['action'], '机器人检测') !== false ||
                                         strpos($log['action'], '测试模式') !== false);
                            $row_class = $is_blocked ? 'blocked' : 'passed';
                            $action_class = $is_blocked ? 'action-blocked' : 'action-redirect';
                        ?>
                        <tr class="<?php echo $row_class; ?>">
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
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px; color: #6c757d;">
                <h4>📝 暂无访问日志</h4>
                <p>当有访问者访问网站时，日志会自动记录在这里</p>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
