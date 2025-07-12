<?php
/**
 * 主管理页面 - 简化版
 * 只包含核心功能：登录、跳转地址设置、统计概览
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

// 修改跳转地址
if (isset($_POST['newurl'])) {
    $new_url = trim($_POST['newurl']);
    if (!empty($new_url)) {
        if (file_put_contents($config['landing_file'], $new_url) !== false) {
            $msg = "跳转地址已更新";
        } else {
            $msg = "更新失败，请检查文件权限";
            $msg_type = 'error';
        }
    } else {
        $msg = "跳转地址不能为空";
        $msg_type = 'error';
    }
}

// 读取当前设置
$current_url = file_exists($config['landing_file']) ? trim(file_get_contents($config['landing_file'])) : '';
$stats = getBlacklistStats($config);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <title>跳转管理后台 - 主页</title>
    <?php echo getCommonStyles(); ?>
</head>
<body>

<div class="main-container">
    <div class="header">
        <h2>🎯 跳转地址后台管理</h2>
        <a href="?logout=1" class="logout-btn">🚪 退出后台</a>
        <?php echo getNavMenu('admin.php'); ?>
    </div>

    <?php showMessage($msg, $msg_type); ?>

    <!-- 系统统计概览 -->
    <div class="card">
        <h3>📊 系统概览</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 15px 0;">
            <div style="text-align: center; padding: 20px; background: rgba(52,152,219,0.1); border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #3498db;"><?php echo $stats['ua_total']; ?></div>
                <div style="font-size: 16px; color: #666; margin-top: 5px;">UA 黑名单条目</div>
                <a href="admin_ua.php" style="font-size: 12px; color: #3498db; text-decoration: none;">→ 管理 UA</a>
            </div>
            <div style="text-align: center; padding: 20px; background: rgba(231,76,60,0.1); border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #e74c3c;"><?php echo $stats['ip_total']; ?></div>
                <div style="font-size: 16px; color: #666; margin-top: 5px;">IP 黑名单条目</div>
                <a href="admin_ip.php" style="font-size: 12px; color: #e74c3c; text-decoration: none;">→ 管理 IP</a>
            </div>
            <div style="text-align: center; padding: 20px; background: rgba(39,174,96,0.1); border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #27ae60;"><?php echo $stats['ip_from_ua']; ?></div>
                <div style="font-size: 16px; color: #666; margin-top: 5px;">从 UA 提取的 IP</div>
                <div style="font-size: 12px; color: #666;">混合格式解析</div>
            </div>
            <div style="text-align: center; padding: 20px; background: rgba(142,68,173,0.1); border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #8e44ad;">
                    <?php echo file_exists($config['log_file']) ? count(file($config['log_file'])) : 0; ?>
                </div>
                <div style="font-size: 16px; color: #666; margin-top: 5px;">访问日志条目</div>
                <a href="admin_monitor.php" style="font-size: 12px; color: #8e44ad; text-decoration: none;">→ 查看日志</a>
            </div>
        </div>
    </div>

    <!-- 跳转地址设置 -->
    <div class="card">
        <h3>🔗 跳转地址设置</h3>
        <form method="post" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <div style="flex: 1; min-width: 300px;">
                <label style="display: block; margin-bottom: 8px; font-weight: 500; color: #2c3e50;">目标跳转地址：</label>
                <input type="text" name="newurl" placeholder="请输入跳转地址" 
                       value="<?php echo htmlspecialchars($current_url); ?>" required 
                       style="width: 100%; padding: 12px 16px; border: 2px solid #e1e8ed; border-radius: 8px; font-size: 14px;" />
            </div>
            <button type="submit" style="padding: 12px 24px; background: linear-gradient(135deg, #3498db, #2980b9); color: white; border: none; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer;">
                💾 保存地址
            </button>
        </form>
        
        <?php if (!empty($current_url)): ?>
            <div style="margin-top: 15px; padding: 10px; background: rgba(52,152,219,0.1); border-radius: 6px;">
                <strong>当前跳转地址：</strong> 
                <a href="<?php echo htmlspecialchars($current_url); ?>" target="_blank" style="color: #3498db; text-decoration: none;">
                    <?php echo htmlspecialchars($current_url); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- 快速操作 -->
    <div class="card">
        <h3>🚀 快速操作</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
            <div style="padding: 20px; border: 2px solid #3498db; border-radius: 8px; text-align: center;">
                <h4 style="color: #3498db; margin-top: 0;">🛡 UA 黑名单管理</h4>
                <p style="color: #666; font-size: 14px;">管理 User-Agent 黑名单，支持文件上传和 API 更新</p>
                <a href="admin_ua.php" style="display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; font-size: 14px;">
                    进入管理
                </a>
            </div>
            
            <div style="padding: 20px; border: 2px solid #e74c3c; border-radius: 8px; text-align: center;">
                <h4 style="color: #e74c3c; margin-top: 0;">🚫 IP 黑名单查看</h4>
                <p style="color: #666; font-size: 14px;">查看从 API 自动提取的 IP 地址黑名单</p>
                <a href="admin_ip.php" style="display: inline-block; padding: 10px 20px; background: #e74c3c; color: white; text-decoration: none; border-radius: 6px; font-size: 14px;">
                    查看 IP
                </a>
            </div>
            
            <div style="padding: 20px; border: 2px solid #8e44ad; border-radius: 8px; text-align: center;">
                <h4 style="color: #8e44ad; margin-top: 0;">📊 系统监控</h4>
                <p style="color: #666; font-size: 14px;">查看访问日志、系统状态和性能统计</p>
                <a href="admin_monitor.php" style="display: inline-block; padding: 10px 20px; background: #8e44ad; color: white; text-decoration: none; border-radius: 6px; font-size: 14px;">
                    查看监控
                </a>
            </div>
            
            <div style="padding: 20px; border: 2px solid #f39c12; border-radius: 8px; text-align: center;">
                <h4 style="color: #f39c12; margin-top: 0;">🧪 测试工具</h4>
                <p style="color: #666; font-size: 14px;">测试黑名单效果，验证系统功能</p>
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 15px;">
                    <a href="ua_tester.php" target="_blank" style="padding: 8px 16px; background: #f39c12; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">
                        UA 测试
                    </a>
                    <a href="check_blacklist.php" target="_blank" style="padding: 8px 16px; background: #f39c12; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">
                        黑名单检查
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- 系统状态 -->
    <div class="card">
        <h3>⚙️ 系统状态</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
            <div>
                <strong>UA 黑名单文件：</strong>
                <span style="color: <?php echo file_exists($config['ua_file']) ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo file_exists($config['ua_file']) ? '✅ 存在' : '❌ 不存在'; ?>
                </span>
            </div>
            <div>
                <strong>IP 黑名单文件：</strong>
                <span style="color: <?php echo file_exists($config['ip_file']) ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo file_exists($config['ip_file']) ? '✅ 存在' : '❌ 不存在'; ?>
                </span>
            </div>
            <div>
                <strong>跳转地址文件：</strong>
                <span style="color: <?php echo file_exists($config['landing_file']) ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo file_exists($config['landing_file']) ? '✅ 存在' : '❌ 不存在'; ?>
                </span>
            </div>
            <div>
                <strong>访问日志文件：</strong>
                <span style="color: <?php echo file_exists($config['log_file']) ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo file_exists($config['log_file']) ? '✅ 存在' : '❌ 不存在'; ?>
                </span>
            </div>
        </div>
    </div>

</div>

</body>
</html>
