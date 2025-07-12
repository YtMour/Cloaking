<?php
/**
 * UA 测试工具
 * 允许您选择黑名单中的 UA 来测试假页面显示
 */

// 读取黑名单中的 UA
function getBlacklistUAs() {
    $uas = [];
    $debug = [];

    if (!file_exists("ua_blacklist.txt")) {
        $debug[] = "文件 ua_blacklist.txt 不存在";
        return ['uas' => $uas, 'debug' => $debug];
    }

    if (!is_readable("ua_blacklist.txt")) {
        $debug[] = "文件 ua_blacklist.txt 不可读";
        return ['uas' => $uas, 'debug' => $debug];
    }

    $content = file_get_contents("ua_blacklist.txt");
    if ($content === false) {
        $debug[] = "无法读取文件内容";
        return ['uas' => $uas, 'debug' => $debug];
    }

    $lines = explode("\n", $content);
    $debug[] = "读取到 " . count($lines) . " 行";

    foreach ($lines as $line_num => $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // 检查是否包含 [ip:xxx] 格式
        if (preg_match('/^(.+?)\s*\[ip:([^\]]+)\]$/', $line, $matches)) {
            $ua_part = trim($matches[1]);
            if (!empty($ua_part)) {
                $uas[] = $ua_part;
                $debug[] = "第" . ($line_num + 1) . "行: 提取混合格式 UA: " . substr($ua_part, 0, 50) . "...";
            }
        } else {
            // 纯 UA 行
            $uas[] = $line;
            $debug[] = "第" . ($line_num + 1) . "行: 纯 UA: " . substr($line, 0, 50) . "...";
        }
    }

    $uas = array_unique($uas);
    $debug[] = "去重后共 " . count($uas) . " 个唯一 UA";

    return ['uas' => $uas, 'debug' => $debug];
}

$ua_result = getBlacklistUAs();
$blacklist_uas = $ua_result['uas'];
$parse_debug = $ua_result['debug'];

// 调试信息
$debug_info = [
    'file_exists' => file_exists("ua_blacklist.txt"),
    'file_readable' => is_readable("ua_blacklist.txt"),
    'ua_count' => count($blacklist_uas),
    'current_dir' => getcwd(),
    'file_size' => file_exists("ua_blacklist.txt") ? filesize("ua_blacklist.txt") : 0,
    'parse_debug' => $parse_debug
];

// 如果选择了 UA，进行测试
$selected_ua = $_GET['ua'] ?? '';
$test_result = '';

if (!empty($selected_ua)) {
    // 使用 cURL 模拟请求
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://ck.ytmour.art/index.php',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERAGENT => $selected_ua,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HEADER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $redirect_url = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    curl_close($ch);
    
    // 分析结果
    if ($http_code == 200) {
        $test_result = [
            'status' => 'blocked',
            'message' => '✅ 成功被阻挡，显示假页面',
            'content' => $response
        ];
    } elseif ($http_code == 302 || $http_code == 301) {
        $test_result = [
            'status' => 'redirected',
            'message' => '❌ 未被阻挡，被重定向到: ' . $redirect_url,
            'content' => $response
        ];
    } else {
        $test_result = [
            'status' => 'error',
            'message' => '⚠️ 测试出错，HTTP状态码: ' . $http_code,
            'content' => $response
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>UA 黑名单测试工具</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; text-align: center; }
        .form-group { margin: 20px 0; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        select { width: 100%; padding: 10px; border: 2px solid #ddd; border-radius: 5px; font-size: 14px; }
        button { background: #007bff; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        button:hover { background: #0056b3; }
        .result { margin: 20px 0; padding: 15px; border-radius: 5px; }
        .result.blocked { background: #d4edda; border: 1px solid #c3e6cb; }
        .result.redirected { background: #f8d7da; border: 1px solid #f5c6cb; }
        .result.error { background: #fff3cd; border: 1px solid #ffeaa7; }
        .content-preview { background: #f8f9fa; padding: 10px; border-radius: 3px; margin-top: 10px; max-height: 300px; overflow-y: auto; font-family: monospace; font-size: 12px; }
        .quick-links { margin: 20px 0; text-align: center; }
        .quick-links a { display: inline-block; margin: 5px 10px; padding: 8px 16px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; }
        .quick-links a:hover { background: #1e7e34; }
        .info-box { background: #d1ecf1; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #17a2b8; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🕵️ UA 黑名单测试工具</h1>
        
        <div class="info-box">
            <h3>📋 使用说明</h3>
            <p>此工具可以帮您测试黑名单中的 UA 是否能正确触发假页面显示。选择一个 UA，点击测试即可查看结果。</p>
        </div>

        <!-- 调试信息 -->
        <div class="info-box" style="background: #fff3cd; border-left-color: #ffc107;">
            <h3>🔍 调试信息</h3>
            <p><strong>ua_blacklist.txt 文件存在:</strong> <?php echo $debug_info['file_exists'] ? '✅ 是' : '❌ 否'; ?></p>
            <p><strong>文件可读:</strong> <?php echo $debug_info['file_readable'] ? '✅ 是' : '❌ 否'; ?></p>
            <p><strong>文件大小:</strong> <?php echo $debug_info['file_size']; ?> 字节</p>
            <p><strong>当前目录:</strong> <?php echo htmlspecialchars($debug_info['current_dir']); ?></p>
            <p><strong>解析到的 UA 数量:</strong> <?php echo $debug_info['ua_count']; ?></p>

            <?php if (!empty($debug_info['parse_debug'])): ?>
                <p><strong>解析过程:</strong></p>
                <div style="max-height: 200px; overflow-y: auto; background: #f8f9fa; padding: 10px; border-radius: 3px; font-family: monospace; font-size: 11px;">
                    <?php foreach (array_slice($debug_info['parse_debug'], 0, 20) as $debug_line): ?>
                        <?php echo htmlspecialchars($debug_line); ?><br>
                    <?php endforeach; ?>
                    <?php if (count($debug_info['parse_debug']) > 20): ?>
                        <em>... 还有 <?php echo count($debug_info['parse_debug']) - 20; ?> 行调试信息</em>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($debug_info['ua_count'] > 0): ?>
                <p><strong>前5个 UA 示例:</strong></p>
                <ul>
                    <?php foreach (array_slice($blacklist_uas, 0, 5) as $ua): ?>
                        <li style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($ua); ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <form method="GET">
            <div class="form-group">
                <label for="ua">选择要测试的 User Agent:</label>
                <select name="ua" id="ua" required>
                    <option value="">-- 请选择一个 UA --</option>
                    <?php foreach ($blacklist_uas as $ua): ?>
                        <option value="<?php echo htmlspecialchars($ua); ?>" 
                                <?php echo ($selected_ua === $ua) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(strlen($ua) > 80 ? substr($ua, 0, 80) . '...' : $ua); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <button type="submit">🧪 开始测试</button>
        </form>

        <?php if (!empty($test_result)): ?>
            <div class="result <?php echo $test_result['status']; ?>">
                <h3>测试结果</h3>
                <p><strong>测试 UA:</strong> <?php echo htmlspecialchars($selected_ua); ?></p>
                <p><strong>结果:</strong> <?php echo $test_result['message']; ?></p>
                
                <?php if ($test_result['status'] === 'blocked'): ?>
                    <div class="content-preview">
                        <strong>假页面内容预览:</strong><br>
                        <?php 
                        // 提取 body 内容
                        if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $test_result['content'], $matches)) {
                            echo htmlspecialchars($matches[1]);
                        } else {
                            echo htmlspecialchars(substr($test_result['content'], 0, 500)) . '...';
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="quick-links">
            <h3>🔗 快速链接</h3>
            <a href="?ua=<?php echo urlencode('AdsBot-Google (+http://www.google.com/adsbot.html)'); ?>">测试 Google AdsBot</a>
            <a href="?ua=<?php echo urlencode('facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)'); ?>">测试 Facebook Bot</a>
            <a href="?ua=<?php echo urlencode('apache-httpclient/4.5.13 (java/17.0.15)'); ?>">测试 Apache HttpClient</a>
        </div>

        <div class="info-box">
            <h3>💡 其他测试方法</h3>
            <p><strong>浏览器开发者工具:</strong></p>
            <ol>
                <li>按 F12 打开开发者工具</li>
                <li>按 Ctrl+Shift+P 打开命令面板</li>
                <li>输入 "user agent" 选择 "Network conditions"</li>
                <li>取消勾选 "Use browser default"</li>
                <li>输入黑名单中的 UA</li>
                <li>刷新页面查看假页面</li>
            </ol>
            
            <p><strong>直接访问假页面:</strong> <a href="index.php?test=fake" target="_blank">点击这里</a></p>
        </div>

        <div style="text-align: center; margin-top: 30px; color: #666; font-size: 14px;">
            <p>共找到 <?php echo count($blacklist_uas); ?> 个不同的 UA 可供测试</p>
        </div>
    </div>
</body>
</html>
