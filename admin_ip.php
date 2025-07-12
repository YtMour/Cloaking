<?php
/**
 * IP 黑名单管理模块
 * 专门处理 IP 地址黑名单的管理，包括从 UA 文件自动提取
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

// 获取 IP 列表
function getIPLists($config) {
    // 从独立 IP 文件读取
    $ips_from_file = file_exists($config['ip_file']) ? 
        file($config['ip_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];
    
    // 从 UA 文件中提取 IP
    $ips_from_ua = [];
    if (file_exists($config['ua_file'])) {
        $ua_lines = file($config['ua_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($ua_lines as $line) {
            $line = trim($line);
            if (preg_match('/\[ip:([^\]]+)\]$/', $line, $matches)) {
                $ip = trim($matches[1]);
                if (!empty($ip)) {
                    $ips_from_ua[] = $ip;
                }
            }
        }
    }
    
    return [
        'from_file' => array_unique($ips_from_file),
        'from_ua' => array_unique($ips_from_ua),
        'all' => array_unique(array_merge($ips_from_file, $ips_from_ua))
    ];
}

// 删除 IP 地址
if (isset($_GET['del_ip'])) {
    $del = urldecode($_GET['del_ip']);
    $list = file_exists($config['ip_file']) ? file($config['ip_file'], FILE_IGNORE_NEW_LINES) : [];
    $list = array_filter($list, fn($x) => trim($x) !== $del);
    file_put_contents($config['ip_file'], implode("\n", $list));
    $msg = "✅ IP 删除成功";
}

// 删除全部 IP
if (isset($_GET['clear_all_ip'])) {
    file_put_contents($config['ip_file'], '');
    $msg = "✅ 已清空所有独立 IP 黑名单";
}

// IP 文件上传功能已删除 - IP 地址从 API 获取的 UA 数据中自动提取

// 从 UA 文件同步 IP 到独立文件
if (isset($_POST['sync_from_ua'])) {
    $ip_lists = getIPLists($config);
    $ips_from_ua = $ip_lists['from_ua'];
    
    if (empty($ips_from_ua)) {
        $msg = "⚠️ UA 文件中没有找到 IP 地址。";
        $msg_type = 'error';
    } else {
        $existing = $ip_lists['from_file'];
        $merged = array_unique(array_merge($existing, $ips_from_ua));
        sort($merged);
        file_put_contents($config['ip_file'], implode("\n", $merged));
        $msg = "✅ 已从 UA 文件同步 " . count($ips_from_ua) . " 个 IP 到独立文件，合并后共 " . count($merged) . " 条。";
    }
}

// 读取 IP 列表
$ip_lists = getIPLists($config);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <title>IP 黑名单管理</title>
    <?php echo getCommonStyles(); ?>
    <style>
        .ip-section { margin-bottom: 30px; }
        
        input[type="text"], input[type="file"] {
            width: 100%;
            padding: 12px 16px;
            margin-top: 8px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            margin-top: 15px;
        }
        
        .btn-primary { background: linear-gradient(135deg, #3498db, #2980b9); color: white; }
        .btn-success { background: linear-gradient(135deg, #27ae60, #229954); color: white; }
        .btn-danger { background: linear-gradient(135deg, #e74c3c, #c0392b); color: white; }
        .btn-warning { background: linear-gradient(135deg, #f39c12, #e67e22); color: white; }
        
        .radio-group {
            margin: 15px 0;
            padding: 15px;
            background: rgba(52,152,219,0.05);
            border-radius: 8px;
        }
        
        .radio-group label {
            display: inline-block;
            margin-right: 25px;
            cursor: pointer;
        }
        
        .ip-list {
            max-height: 400px;
            overflow-y: auto;
            background: rgba(255,255,255,0.95);
            border-radius: 8px;
            padding: 15px;
        }
        
        .ip-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #ecf0f1;
            font-family: monospace;
            font-size: 14px;
        }
        
        .ip-item:last-child { border-bottom: none; }
        
        .ip-source {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 3px;
            margin-left: 10px;
        }
        
        .source-file { background: #e3f2fd; color: #1976d2; }
        .source-ua { background: #f3e5f5; color: #7b1fa2; }
        .source-both { background: #e8f5e8; color: #388e3c; }
        
        .delete-link {
            color: #e74c3c;
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        
        .delete-link:hover {
            background-color: #e74c3c;
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid;
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="header">
        <h2>🚫 IP 黑名单管理</h2>
        <a href="?logout=1" class="logout-btn">🚪 退出后台</a>
        <?php echo getNavMenu('admin_ip.php'); ?>
    </div>

    <?php showMessage($msg, $msg_type); ?>

    <!-- IP 统计概览 -->
    <div class="card">
        <h3>📊 IP 黑名单统计</h3>
        <div class="stats-grid">
            <div class="stat-card" style="border-color: #3498db; background: rgba(52,152,219,0.1);">
                <div style="font-size: 24px; font-weight: bold; color: #3498db;"><?php echo count($ip_lists['all']); ?></div>
                <div style="font-size: 14px; color: #666;">总 IP 数量</div>
            </div>
            <div class="stat-card" style="border-color: #1976d2; background: rgba(25,118,210,0.1);">
                <div style="font-size: 24px; font-weight: bold; color: #1976d2;"><?php echo count($ip_lists['from_file']); ?></div>
                <div style="font-size: 14px; color: #666;">独立文件 IP</div>
            </div>
            <div class="stat-card" style="border-color: #7b1fa2; background: rgba(123,31,162,0.1);">
                <div style="font-size: 24px; font-weight: bold; color: #7b1fa2;"><?php echo count($ip_lists['from_ua']); ?></div>
                <div style="font-size: 14px; color: #666;">UA 文件提取 IP</div>
            </div>
        </div>
    </div>

    <!-- IP 来源说明 -->
    <div class="card ip-section">
        <h3>📋 IP 黑名单来源说明</h3>
        <div style="padding: 15px; background: rgba(52,152,219,0.1); border-radius: 8px;">
            <p style="margin: 0 0 10px 0;"><strong>🔄 自动提取：</strong>IP 地址从 API 获取的 UA 数据中自动提取</p>
            <p style="margin: 0 0 10px 0;"><strong>📝 格式识别：</strong>系统会解析 <code>[ip:xxx.xxx.xxx.xxx]</code> 格式的 IP 地址</p>
            <p style="margin: 0;"><strong>🔄 实时更新：</strong>每次执行 UA API 更新时，IP 黑名单也会自动更新</p>
        </div>
    </div>

    <!-- 从 UA 文件同步 -->
    <div class="card ip-section">
        <h3>🔄 从 UA 文件同步 IP</h3>
        <p style="color: #666; margin-bottom: 15px;">
            将 UA 黑名单文件中的 IP 地址（[ip:xxx] 格式）同步到独立的 IP 黑名单文件中。
        </p>
        <form method="post">
            <button type="submit" name="sync_from_ua" class="btn btn-warning"
                    onclick="return confirm('确认将 UA 文件中的 IP 同步到独立 IP 文件吗？')">
                🔄 同步 IP (<?php echo count($ip_lists['from_ua']); ?> 个)
            </button>
        </form>
    </div>

    <!-- 当前 IP 黑名单 -->
    <div class="card ip-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>📋 当前 IP 黑名单 (<?php echo count($ip_lists['all']); ?> 条)</h3>
            <?php if (!empty($ip_lists['from_file'])): ?>
                <button onclick="if(confirm('⚠️ 确定要清空独立 IP 黑名单文件吗？UA 文件中的 IP 不会被删除。')){location.href='?clear_all_ip=1';}"
                        class="btn btn-danger">
                    🗑️ 清空独立文件
                </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($ip_lists['all'])): ?>
            <div class="ip-list">
                <?php 
                $from_file_set = array_flip($ip_lists['from_file']);
                $from_ua_set = array_flip($ip_lists['from_ua']);
                
                foreach($ip_lists['all'] as $ip): 
                    $in_file = isset($from_file_set[$ip]);
                    $in_ua = isset($from_ua_set[$ip]);
                    
                    if ($in_file && $in_ua) {
                        $source = '<span class="ip-source source-both">文件+UA</span>';
                        $can_delete = true;
                    } elseif ($in_file) {
                        $source = '<span class="ip-source source-file">独立文件</span>';
                        $can_delete = true;
                    } else {
                        $source = '<span class="ip-source source-ua">UA文件</span>';
                        $can_delete = false;
                    }
                ?>
                    <div class="ip-item">
                        <span>
                            <?php echo htmlspecialchars($ip); ?>
                            <?php echo $source; ?>
                        </span>
                        <?php if ($can_delete): ?>
                            <a href="?del_ip=<?php echo urlencode($ip); ?>"
                               class="delete-link"
                               onclick="return confirm('确定删除此 IP 吗？')">删除</a>
                        <?php else: ?>
                            <span style="color: #999; font-size: 12px;">来自UA文件</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div style="margin-top: 15px; padding: 10px; background: rgba(52,152,219,0.1); border-radius: 6px; font-size: 14px;">
                <strong>说明：</strong>
                <ul style="margin: 5px 0; padding-left: 20px;">
                    <li><span class="ip-source source-file">独立文件</span> - 可以删除，来自 ip_blacklist.txt</li>
                    <li><span class="ip-source source-ua">UA文件</span> - 不可删除，来自 ua_blacklist.txt 的 [ip:xxx] 格式</li>
                    <li><span class="ip-source source-both">文件+UA</span> - 可以删除独立文件中的副本</li>
                </ul>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px; color: #6c757d;">
                <h4>📝 暂无 IP 黑名单记录</h4>
                <p>您可以通过上传文件或从 UA 文件同步来添加 IP 黑名单</p>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
