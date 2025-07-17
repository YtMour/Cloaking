<?php
/**
 * Cloak 系统管理后台入口
 * 提供模块化的管理界面路由
 * 
 * 支持的模块：
 * - dashboard: 仪表板
 * - monitor: 监控日志
 * - ua: UA管理
 * - ip: IP管理
 * - tools: 工具
 */

// 引入核心模块
require_once 'Cloak_Core/Autoloader.php';

use Cloak\Core\Config;
use Cloak\Core\Auth;
use Cloak\Core\Logger;

// 设置数据路径
define('CLOAK_DATA_PATH', 'Cloak_Data/');

try {
    // 初始化配置和认证
    $config = Config::getInstance();
    $auth = new Auth();
    $logger = new Logger();
    
    // 处理登出
    if (isset($_GET['action']) && $_GET['action'] === 'logout') {
        $auth->logout();
        header('Location: Cloak_admin.php');
        exit;
    }
    
    // 处理登录请求
    $auth->handleLoginRequest();
    
    // 要求登录
    $auth->requireLogin();
    
    // 获取请求的模块
    $module = $_GET['module'] ?? 'dashboard';
    $action = $_GET['action'] ?? 'index';
    
    // 验证模块名称
    $allowedModules = ['dashboard', 'monitor', 'ua', 'ip', 'tools'];
    if (!in_array($module, $allowedModules)) {
        $module = 'dashboard';
    }
    
    // 路由到对应的控制器
    switch ($module) {
        case 'monitor':
            require_once 'Cloak_Admin/Controllers/MonitorController.php';

            // 创建配置数组 - 按照原始格式
            $monitor_config = [
                'log_file' => 'Cloak_Data/log.txt',
                'ip_file' => 'Cloak_Data/ip_blacklist.txt',
                'ua_file' => 'Cloak_Data/ua_blacklist.txt',
                'landing_file' => 'Cloak_Data/real_landing_url.txt',
                'api_config_file' => 'Cloak_Data/api_config.json'
            ];

            $controller = new MonitorController($monitor_config);
            $data = $controller->index();

            // 设置消息
            if (!empty($data['message'])) {
                $_SESSION['message'] = $data['message'];
                $_SESSION['message_type'] = $data['message_type'];
            }

            // 传递数据到视图
            $logs = $data['logs'];
            $pagination = $data['pagination'];
            $stats = $data['stats'];
            $system_info = $data['system_info'];

            // 包含头部模板
            include 'Cloak_Admin/Views/Templates/header.php';

            // 包含监控视图
            include 'Cloak_Admin/Views/monitor.php';

            // 包含底部模板
            include 'Cloak_Admin/Views/Templates/footer.php';
            break;
            
        case 'ua':
            require_once 'Cloak_Admin/Controllers/UAController.php';
            $controller = new \Cloak\Admin\Controllers\UAController();
            $controller->index();
            break;

        case 'ip':
            require_once 'Cloak_Admin/Controllers/IPController.php';
            $controller = new \Cloak\Admin\Controllers\IPController();
            $controller->index();
            break;
            
        case 'tools':
            require_once 'Cloak_Admin/Controllers/ToolsController.php';
            $controller = new \Cloak\Admin\Controllers\ToolsController();

            // 处理子页面
            $subpage = $_GET['subpage'] ?? 'index';
            switch ($subpage) {
                case 'blacklist_check':
                    $controller->blacklistCheck();
                    break;
                case 'ua_test':
                    $controller->uaTest();
                    break;
                case 'ip_test':
                    $controller->ipTest();
                    break;
                default:
                    $controller->index();
                    break;
            }
            break;
            
        case 'dashboard':
        default:
            showDashboard();
            break;
    }
    
} catch (Exception $e) {
    // 错误处理
    $logger->logError("管理后台错误: " . $e->getMessage());
    
    echo "<!DOCTYPE html>
<html>
<head>
    <title>系统错误</title>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .error { background: white; padding: 30px; border-radius: 10px; display: inline-block; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class='error'>
        <h2>❌ 系统错误</h2>
        <p>管理后台遇到错误，请稍后再试。</p>
        <a href='Cloak_admin.php'>返回首页</a>
    </div>
</body>
</html>";
}

/**
 * 显示仪表板 - 完全按照原始back/admin.php样式
 */
function showDashboard() {
    $title = '斗篷管理后台 - 主页';
    $module = 'dashboard';
    $msg = '';
    $msg_type = 'success';

    // 获取配置
    $config = Config::getInstance();

    // 修改跳转地址
    if (isset($_POST['newurl'])) {
        $new_url = trim($_POST['newurl']);
        if (!empty($new_url)) {
            if ($config->setLandingURL($new_url)) {
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
    $current_url = $config->getLandingURL();
    $stats = getBlacklistStats();

    include 'Cloak_Admin/Views/Templates/header.php';
    ?>

    <!-- 系统统计概览 -->
    <div class="card">
        <h3>📊 系统概览</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 15px 0;">
            <div style="text-align: center; padding: 20px; background: rgba(52,152,219,0.1); border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #3498db;"><?php echo $stats['ua_total']; ?></div>
                <div style="font-size: 16px; color: #666; margin-top: 5px;">UA 黑名单条目</div>
                <a href="Cloak_admin.php?module=ua" style="font-size: 12px; color: #3498db; text-decoration: none;">→ 管理 UA</a>
            </div>
            <div style="text-align: center; padding: 20px; background: rgba(231,76,60,0.1); border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #e74c3c;"><?php echo $stats['ip_total']; ?></div>
                <div style="font-size: 16px; color: #666; margin-top: 5px;">IP 黑名单条目</div>
                <a href="Cloak_admin.php?module=ip" style="font-size: 12px; color: #e74c3c; text-decoration: none;">→ 管理 IP</a>
            </div>
            <div style="text-align: center; padding: 20px; background: rgba(39,174,96,0.1); border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #27ae60;"><?php echo $stats['ip_from_ua']; ?></div>
                <div style="font-size: 16px; color: #666; margin-top: 5px;">从 UA 提取的 IP</div>
                <div style="font-size: 12px; color: #666;">混合格式解析</div>
            </div>
            <div style="text-align: center; padding: 20px; background: rgba(142,68,173,0.1); border-radius: 8px;">
                <div style="font-size: 32px; font-weight: bold; color: #8e44ad;">
                    <?php echo file_exists($config->getLogPath()) ? count(file($config->getLogPath())) : 0; ?>
                </div>
                <div style="font-size: 16px; color: #666; margin-top: 5px;">访问日志条目</div>
                <a href="Cloak_admin.php?module=monitor" style="font-size: 12px; color: #8e44ad; text-decoration: none;">→ 查看日志</a>
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
                <a href="Cloak_admin.php?module=ua" style="display: inline-block; padding: 10px 20px; background: #3498db; color: white; text-decoration: none; border-radius: 6px; font-size: 14px;">
                    进入管理
                </a>
            </div>

            <div style="padding: 20px; border: 2px solid #e74c3c; border-radius: 8px; text-align: center;">
                <h4 style="color: #e74c3c; margin-top: 0;">🚫 IP 黑名单查看</h4>
                <p style="color: #666; font-size: 14px;">查看从 API 自动提取的 IP 地址黑名单</p>
                <a href="Cloak_admin.php?module=ip" style="display: inline-block; padding: 10px 20px; background: #e74c3c; color: white; text-decoration: none; border-radius: 6px; font-size: 14px;">
                    查看 IP
                </a>
            </div>

            <div style="padding: 20px; border: 2px solid #8e44ad; border-radius: 8px; text-align: center;">
                <h4 style="color: #8e44ad; margin-top: 0;">📊 系统监控</h4>
                <p style="color: #666; font-size: 14px;">查看访问日志、系统状态和性能统计</p>
                <a href="Cloak_admin.php?module=monitor" style="display: inline-block; padding: 10px 20px; background: #8e44ad; color: white; text-decoration: none; border-radius: 6px; font-size: 14px;">
                    查看监控
                </a>
            </div>

            <div style="padding: 20px; border: 2px solid #f39c12; border-radius: 8px; text-align: center;">
                <h4 style="color: #f39c12; margin-top: 0;">🧪 测试工具</h4>
                <p style="color: #666; font-size: 14px;">测试黑名单效果，验证系统功能</p>
                <div style="display: flex; gap: 10px; justify-content: center; margin-top: 15px;">
                    <a href="Cloak_admin.php?module=tools&subpage=ua_test" style="padding: 8px 16px; background: #f39c12; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">
                        UA 测试
                    </a>
                    <a href="Cloak_admin.php?module=tools&subpage=blacklist_check" style="padding: 8px 16px; background: #f39c12; color: white; text-decoration: none; border-radius: 4px; font-size: 13px;">
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
                <span style="color: <?php echo file_exists($config->getUABlacklistPath()) ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo file_exists($config->getUABlacklistPath()) ? '✅ 存在' : '❌ 不存在'; ?>
                </span>
            </div>
            <div>
                <strong>IP 黑名单文件：</strong>
                <span style="color: <?php echo file_exists($config->getIPBlacklistPath()) ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo file_exists($config->getIPBlacklistPath()) ? '✅ 存在' : '❌ 不存在'; ?>
                </span>
            </div>
            <div>
                <strong>跳转地址文件：</strong>
                <span style="color: <?php echo file_exists($config->getLandingURLPath()) ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo file_exists($config->getLandingURLPath()) ? '✅ 存在' : '❌ 不存在'; ?>
                </span>
            </div>
            <div>
                <strong>访问日志文件：</strong>
                <span style="color: <?php echo file_exists($config->getLogPath()) ? '#27ae60' : '#e74c3c'; ?>;">
                    <?php echo file_exists($config->getLogPath()) ? '✅ 存在' : '❌ 不存在'; ?>
                </span>
            </div>
        </div>
    </div>

</div>

</body>
</html>
    <?php
}





/**
 * 获取黑名单统计信息
 */
function getBlacklistStats() {
    $config = Config::getInstance();

    $stats = [
        'ua_total' => 0,
        'ip_total' => 0,
        'ip_from_ua' => 0
    ];

    // UA黑名单统计
    $uaFile = $config->getUABlacklistPath();
    if (file_exists($uaFile)) {
        $uaLines = file($uaFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats['ua_total'] = count($uaLines);

        // 统计从UA中提取的IP
        foreach ($uaLines as $line) {
            if (preg_match('/\[ip:([^\]]+)\]$/', trim($line))) {
                $stats['ip_from_ua']++;
            }
        }
    }

    // IP黑名单统计
    $ipFile = $config->getIPBlacklistPath();
    if (file_exists($ipFile)) {
        $ipLines = file($ipFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $stats['ip_total'] = count($ipLines);
    }

    return $stats;
}

/**
 * 格式化字节数
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}
?>
