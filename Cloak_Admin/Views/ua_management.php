<?php
$title = 'UA黑名单管理 - Cloak 管理后台';
$module = 'ua';
?>

<div class="card">
    <h3>🚫 User-Agent 黑名单管理</h3>
    <p style="color: #666; margin-bottom: 25px;">
        管理 User-Agent 黑名单，支持手动添加、文件上传和 API 自动更新。当前共有 <strong><?php echo count($uas ?? []); ?></strong> 条记录。
    </p>

    <!-- 手动添加UA -->
    <div style="background: rgba(52,152,219,0.1); padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(52,152,219,0.3);">
        <h4 style="margin: 0 0 15px 0; color: #2c3e50;">➕ 手动添加 User-Agent</h4>
        <form method="post">
            <div class="form-group">
                <label>User-Agent 字符串：</label>
                <input type="text" name="new_ua" placeholder="输入要添加的User-Agent" required style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 6px; font-size: 14px;">
            </div>
            <button type="submit" name="add_ua" class="btn btn-primary">添加到黑名单</button>
        </form>
    </div>

    <!-- 文件上传 -->
    <div style="background: rgba(39,174,96,0.1); padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(39,174,96,0.3);">
        <h4 style="margin: 0 0 15px 0; color: #2c3e50;">📁 文件上传</h4>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>选择 UA 黑名单文件 (txt格式)：</label>
                <input type="file" name="ua_file" accept=".txt" required style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 6px; font-size: 14px;">
            </div>

            <div style="margin: 15px 0; padding: 15px; background: rgba(255,255,255,0.7); border-radius: 6px;">
                <label style="display: block; margin-bottom: 10px; font-weight: 600;">上传模式：</label>
                <label style="display: inline-block; margin-right: 25px; cursor: pointer; font-weight: normal;">
                    <input type="radio" name="upload_type" value="merge" checked> 合并模式（保留现有数据）
                </label>
                <label style="display: inline-block; cursor: pointer; font-weight: normal;">
                    <input type="radio" name="upload_type" value="cover"> 覆盖模式（替换所有数据）
                </label>
            </div>

            <button type="submit" name="upload_ua" class="btn btn-success">上传文件</button>
        </form>
    </div>

    <!-- API自动更新 -->
    <div style="background: rgba(243,156,18,0.1); padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(243,156,18,0.3);">
        <h4 style="margin: 0 0 15px 0; color: #2c3e50;">🔄 API 自动更新</h4>
        <form method="post">
            <div class="form-group">
                <label>API 地址：</label>
                <input type="text" name="api_url" value="<?php echo htmlspecialchars($api_config['api_url'] ?? 'https://user-agents.net/download'); ?>"
                       placeholder="https://api.example.com/ua-list" style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 6px; font-size: 14px;">
            </div>

            <div class="form-group">
                <label>API 参数：</label>
                <input type="text" name="api_params" value="<?php echo htmlspecialchars($api_config['api_params'] ?? 'crawler=true&limit=500&download=txt'); ?>"
                       placeholder="参数格式：key1=value1&key2=value2" style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 6px; font-size: 14px;">
            </div>

            <button type="submit" name="auto_update_ua" class="btn btn-warning">执行自动更新</button>

            <?php if (isset($api_config['last_updated'])): ?>
                <div style="margin-top: 15px; padding: 10px; background: rgba(255,255,255,0.7); border-radius: 6px; font-size: 13px; color: #666;">
                    <strong>上次更新：</strong><?php echo $api_config['last_updated']; ?>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <!-- UA列表 -->
    <div style="background: rgba(155,89,182,0.1); padding: 20px; border-radius: 8px; border: 1px solid rgba(155,89,182,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="margin: 0; color: #2c3e50;">📋 当前黑名单</h4>
            <div style="display: flex; gap: 10px;">
                <a href="?module=ua&action=clear_all" class="btn btn-danger" style="text-decoration: none; font-size: 12px; padding: 8px 16px;"
                   onclick="return confirm('确定要清空所有UA黑名单吗？此操作不可恢复！')">清空所有</a>
                <button onclick="exportUAList()" class="btn btn-success" style="font-size: 12px; padding: 8px 16px;">导出列表</button>
            </div>
        </div>

        <?php if (!empty($uas)): ?>
            <div style="max-height: 400px; overflow-y: auto; background: rgba(255,255,255,0.9); border-radius: 6px; padding: 15px;">
                <table class="table" style="font-size: 12px;">
                    <thead>
                        <tr>
                            <th style="width: 60%;">User-Agent</th>
                            <th style="width: 20%;">关联IP</th>
                            <th style="width: 20%;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($uas as $index => $ua):
                            $hasIP = preg_match('/\[ip:([^\]]+)\]$/', $ua, $matches);
                            $displayUA = $hasIP ? preg_replace('/\s*\[ip:[^\]]+\]$/', '', $ua) : $ua;
                            $ipAddress = $hasIP ? $matches[1] : '';
                        ?>
                        <tr>
                            <td style="font-family: monospace; font-size: 12px; word-break: break-all;">
                                <?php echo htmlspecialchars($displayUA); ?>
                            </td>
                            <td style="font-size: 12px;">
                                <?php if ($hasIP): ?>
                                    <span style="color: #e74c3c; font-family: monospace;"><?php echo htmlspecialchars($ipAddress); ?></span>
                                <?php else: ?>
                                    <span style="color: #95a5a6;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="?module=ua&action=delete&ua=<?php echo urlencode($ua); ?>"
                                   class="btn btn-danger"
                                   style="padding: 4px 8px; font-size: 11px; text-decoration: none;"
                                   onclick="return confirm('确定要删除这个UA吗？')">删除</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div style="text-align: center; margin-top: 15px; color: #666; font-size: 13px;">
                共显示 <strong><?php echo count($uas); ?></strong> 条记录
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <h4>📭 暂无UA黑名单</h4>
                <p style="margin: 10px 0 0 0;">您可以通过上传文件、API更新或手动添加来建立黑名单。</p>
            </div>
        <?php endif; ?>
    </div>
</div>



<script>
// 导出UA列表
function exportUAList() {
    const uas = <?php echo json_encode($uas ?? []); ?>;
    const content = uas.join('\n');
    const blob = new Blob([content], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'ua_blacklist_' + new Date().toISOString().slice(0, 10) + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    alert('UA黑名单已导出');
}
</script>
