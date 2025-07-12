<?php
/**
 * UA 黑名单管理模块
 * 专门处理 User-Agent 黑名单的增删改查
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

// 删除 UA 关键词
if (isset($_GET['del_ua'])) {
    $del = urldecode($_GET['del_ua']);
    $list = file_exists($config['ua_file']) ? file($config['ua_file'], FILE_IGNORE_NEW_LINES) : [];
    $list = array_filter($list, fn($x) => $x !== $del);
    file_put_contents($config['ua_file'], implode("\n", $list));
    $msg = "✅ UA 删除成功";
}

// 删除全部 UA
if (isset($_GET['clear_all_ua'])) {
    file_put_contents($config['ua_file'], '');
    $msg = "✅ 已清空所有 UA 黑名单";
}

// 文件上传处理
if (isset($_POST['upload_ua'])) {
    $upload_type = $_POST['upload_type'] ?? 'merge';

    if (!isset($_FILES['ua_file']) || $_FILES['ua_file']['error'] !== UPLOAD_ERR_OK) {
        $msg = "❌ 上传失败，请重试。";
        $msg_type = 'error';
    } else {
        $file = $_FILES['ua_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'txt') {
            $msg = "❌ 只允许上传 txt 文件。";
            $msg_type = 'error';
        } else {
            $content = file_get_contents($file['tmp_name']);
            $lines = array_filter(array_map('trim', explode("\n", $content)));
            $lines = array_unique(array_map('strtolower', $lines));
            if (empty($lines)) {
                $msg = "❌ 上传的文件内容为空。";
                $msg_type = 'error';
            } else {
                if ($upload_type === 'cover') {
                    file_put_contents($config['ua_file'], implode("\n", $lines));
                    $msg = "✅ 上传覆盖成功，UA 黑名单共 " . count($lines) . " 条。";
                } else {
                    $existing = file_exists($config['ua_file']) ? file($config['ua_file'], FILE_IGNORE_NEW_LINES) : [];
                    $merged = array_unique(array_merge($existing, $lines));
                    sort($merged);
                    file_put_contents($config['ua_file'], implode("\n", $merged));
                    $msg = "✅ 上传合并成功，UA 黑名单共有 " . count($merged) . " 条。";
                }
            }
        }
    }
}

// API 自动更新
if (isset($_POST['auto_update_ua'])) {
    $api_url = trim($_POST['api_url'] ?? '');
    $api_params = trim($_POST['api_params'] ?? '');

    if (empty($api_url)) {
        $msg = "❌ API 地址不能为空。";
        $msg_type = 'error';
    } else {
        // 保存 API 配置
        if (saveAPIConfig($config['api_config_file'], $api_url, $api_params)) {
            // API 配置保存成功，继续执行更新
        }
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $api_params);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36");
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        if ($http_code === 200 && $response !== false && strlen($response) > 10) {
            $new_lines = array_filter(array_map('trim', explode("\n", $response)));
            $new_lines = array_unique(array_map('strtolower', $new_lines));
            if (!empty($new_lines)) {
                $existing = file_exists($config['ua_file']) ? file($config['ua_file'], FILE_IGNORE_NEW_LINES) : [];
                $merged = array_unique(array_merge($existing, $new_lines));
                sort($merged);
                file_put_contents($config['ua_file'], implode("\n", $merged));

                // 自动提取并保存 IP
                $ip_count = extractAndSaveIPs($config['ua_file'], $config['ip_file']);

                $msg = "✅ 自动更新成功！从 API 获取到 " . count($new_lines) . " 条新 UA，合并后黑名单共有 " . count($merged) . " 条。";
                if ($ip_count !== false) {
                    $msg .= " 同时提取并保存了 " . $ip_count . " 个 IP 地址。";
                }
            } else {
                $msg = "⚠️ API 返回结果为空，未更新任何 UA。";
                $msg_type = 'error';
            }
        } else {
            $msg = "❌ API 请求失败，HTTP 状态码: $http_code" . ($curl_err ? "，错误: $curl_err" : "");
            $msg_type = 'error';
        }
    }
}

// 读取当前 UA 列表
$uas = file_exists($config['ua_file']) ? file($config['ua_file'], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) : [];

// 读取 API 配置
$api_config = getAPIConfig($config['api_config_file']);

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8" />
    <title>UA 黑名单管理</title>
    <?php echo getCommonStyles(); ?>
    <style>
        .upload-section, .api-section, .list-section {
            margin-bottom: 30px;
        }
        
        input[type="text"], input[type="file"] {
            width: 100%;
            padding: 12px 16px;
            margin-top: 8px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }
        
        input[type="text"]:focus, input[type="file"]:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
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
        
        .btn:hover { transform: translateY(-2px); }
        
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
        
        .ua-list {
            max-height: 500px;
            overflow-y: auto;
            background: rgba(255,255,255,0.95);
            border-radius: 8px;
            padding: 15px;
        }
        
        .ua-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ecf0f1;
            font-family: monospace;
            font-size: 13px;
        }
        
        .ua-item:last-child { border-bottom: none; }
        
        .delete-link {
            color: #e74c3c;
            text-decoration: none;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            transition: all 0.2s ease;
        }
        
        .delete-link:hover {
            background-color: #e74c3c;
            color: white;
        }
    </style>
</head>
<body>

<div class="main-container">
    <div class="header">
        <h2>🛡 UA 黑名单管理</h2>
        <a href="?logout=1" class="logout-btn">🚪 退出后台</a>
        <?php echo getNavMenu('admin_ua.php'); ?>
    </div>

    <?php showMessage($msg, $msg_type); ?>

    <!-- 文件上传管理 -->
    <div class="card upload-section">
        <h3>📁 文件上传管理</h3>
        <form method="post" enctype="multipart/form-data">
            <label>选择 UA 黑名单 txt 文件：</label>
            <input type="file" name="ua_file" accept=".txt" required />

            <div class="radio-group">
                <label>
                    <input type="radio" name="upload_type" value="merge" checked /> 🔄 合并到现有黑名单
                </label>
                <label>
                    <input type="radio" name="upload_type" value="cover" /> 🔁 覆盖现有黑名单
                </label>
            </div>

            <button type="submit" name="upload_ua" class="btn btn-primary">📤 上传文件</button>
        </form>
    </div>

    <!-- API 自动更新 -->
    <div class="card api-section">
        <h3>🔄 API 自动更新配置</h3>
        <form method="post">
            <label>API 地址：</label>
            <input type="text" name="api_url" placeholder="https://user-agents.net/download"
                   value="<?php echo htmlspecialchars($api_config['api_url']); ?>" required />

            <label>POST 参数：</label>
            <input type="text" name="api_params" placeholder="crawler=true&limit=500&download=txt"
                   value="<?php echo htmlspecialchars($api_config['api_params']); ?>" required />

            <button type="submit" name="auto_update_ua" class="btn btn-success"
                    onclick="return confirm('确认从 API 拉取最新爬虫 UA 并合并到黑名单吗？\n\n配置将自动保存。')">
                🚀 执行自动更新
            </button>
        </form>

        <?php if (file_exists($config['api_config_file'])): ?>
            <div style="margin-top: 15px; padding: 10px; background: rgba(52,152,219,0.1); border-radius: 6px; font-size: 14px;">
                <strong>💾 配置已保存</strong> - 参数会在下次访问时自动填入
                <?php
                $saved_config = json_decode(file_get_contents($config['api_config_file']), true);
                if ($saved_config && isset($saved_config['last_updated'])) {
                    echo '<br><small style="color: #666;">最后更新: ' . htmlspecialchars($saved_config['last_updated']) . '</small>';
                }
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- 当前黑名单 -->
    <div class="card list-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3>📋 当前 UA 黑名单 (<?php echo count($uas); ?> 条)</h3>
            <?php if (!empty($uas)): ?>
                <button onclick="if(confirm('⚠️ 确定要清空所有 UA 黑名单吗？此操作不可恢复！')){location.href='?clear_all_ua=1';}"
                        class="btn btn-danger">
                    🗑️ 清空全部
                </button>
            <?php endif; ?>
        </div>

        <?php if (!empty($uas)): ?>
            <div class="ua-list">
                <?php foreach($uas as $ua): ?>
                    <div class="ua-item">
                        <span><?php echo htmlspecialchars($ua); ?></span>
                        <a href="?del_ua=<?php echo urlencode($ua); ?>"
                           class="delete-link"
                           onclick="return confirm('确定删除此 UA 吗？')">删除</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 60px; color: #6c757d;">
                <h4>📝 暂无 UA 黑名单记录</h4>
                <p>您可以通过上传文件或 API 更新来添加黑名单</p>
            </div>
        <?php endif; ?>
    </div>

</div>

</body>
</html>
