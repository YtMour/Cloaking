<?php
$title = 'IP黑名单管理 - Cloak 管理后台';
$module = 'ip';
?>

<div class="card">
    <h3>🌐 IP 黑名单管理</h3>
    <p style="color: #666; margin-bottom: 25px;">
        管理 IP 地址黑名单，支持手动添加、文件上传和从UA文件同步。当前共有 <strong><?php echo count($ip_lists['all'] ?? []); ?></strong> 个IP地址。
    </p>

    <!-- 手动添加IP -->
    <div style="background: rgba(52,152,219,0.1); padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(52,152,219,0.3);">
        <h4 style="margin: 0 0 15px 0; color: #2c3e50;">➕ 手动添加 IP 地址</h4>
        <form method="post">
            <div class="form-group">
                <label>IP 地址：</label>
                <input type="text" name="new_ip" placeholder="输入要添加的IP地址 (支持IPv4和IPv6)" required style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 6px; font-size: 14px;">
            </div>
            <button type="submit" name="add_ip" class="btn btn-primary">添加到黑名单</button>
        </form>
    </div>

    <!-- 同步操作 -->
    <div style="background: rgba(39,174,96,0.1); padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(39,174,96,0.3);">
        <h4 style="margin: 0 0 15px 0; color: #2c3e50;">🔄 同步操作</h4>
        <form method="post">
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <button type="submit" name="sync_from_ua" class="btn btn-warning">
                    从UA文件同步IP (<?php echo $stats['from_ua']; ?> 个)
                </button>
                <div style="font-size: 13px; color: #666;">
                    将UA黑名单中的IP地址同步到独立的IP黑名单文件
                </div>
            </div>
        </form>
    </div>

    <!-- 文件上传 -->
    <div style="background: rgba(243,156,18,0.1); padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid rgba(243,156,18,0.3);">
        <h4 style="margin: 0 0 15px 0; color: #2c3e50;">📁 文件上传</h4>
        <form method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>选择 IP 黑名单文件 (txt格式)：</label>
                <input type="file" name="ip_file" accept=".txt" required style="width: 100%; padding: 12px; border: 2px solid #e1e8ed; border-radius: 6px; font-size: 14px;">
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

            <button type="submit" name="upload_ip" class="btn btn-warning">上传文件</button>
        </form>
    </div>

    <!-- IP列表 -->
    <div style="background: rgba(155,89,182,0.1); padding: 20px; border-radius: 8px; border: 1px solid rgba(155,89,182,0.3);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h4 style="margin: 0; color: #2c3e50;">📋 当前黑名单</h4>
            <div style="display: flex; gap: 10px;">
                <input type="text" id="ip-search" placeholder="搜索IP地址..." style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;" onkeyup="filterIPList()">
                <a href="?module=ip&action=clear_all" class="btn btn-danger" style="text-decoration: none; font-size: 12px; padding: 8px 16px;"
                   onclick="return confirm('确定要清空所有独立IP黑名单吗？此操作不可恢复！')">清空独立文件</a>
                <button onclick="exportIPList()" class="btn btn-success" style="font-size: 12px; padding: 8px 16px;">导出列表</button>
            </div>
        </div>

        <!-- 标签页 -->
        <div style="margin-bottom: 20px;">
            <button class="tab-btn active" onclick="showTab('all')" style="padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; margin-right: 10px; font-size: 12px; cursor: pointer;">全部 (<?php echo $stats['total']; ?>)</button>
            <button class="tab-btn" onclick="showTab('file')" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; margin-right: 10px; font-size: 12px; cursor: pointer;">独立文件 (<?php echo $stats['from_file']; ?>)</button>
            <button class="tab-btn" onclick="showTab('ua')" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; font-size: 12px; cursor: pointer;">来自UA (<?php echo $stats['from_ua']; ?>)</button>
        </div>

        <?php if (!empty($ip_lists['all'])): ?>
            <!-- 全部IP -->
            <div id="tab-all" class="tab-content active">
                <div style="max-height: 400px; overflow-y: auto; background: rgba(255,255,255,0.9); border-radius: 6px; padding: 15px;">
                    <table class="table" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th style="width: 40%;">IP 地址</th>
                                <th style="width: 30%;">来源</th>
                                <th style="width: 15%;">类型</th>
                                <th style="width: 15%;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ip_lists['all'] as $ip):
                                $inFile = in_array($ip, $ip_lists['from_file']);
                                $inUA = in_array($ip, $ip_lists['from_ua']);
                                $source = [];
                                if ($inFile) $source[] = '独立文件';
                                if ($inUA) $source[] = 'UA文件';
                                $sourceText = implode(' + ', $source);
                                $ipType = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'IPv4' : 'IPv6';
                            ?>
                            <tr>
                                <td style="font-family: monospace; font-size: 12px; font-weight: 600;">
                                    <?php echo htmlspecialchars($ip); ?>
                                </td>
                                <td style="font-size: 12px;">
                                    <span style="color: <?php echo $inFile ? '#3498db' : '#e74c3c'; ?>;">
                                        <?php echo $sourceText; ?>
                                    </span>
                                </td>
                                <td style="font-size: 12px;">
                                    <span style="padding: 2px 6px; background: <?php echo $ipType === 'IPv4' ? 'rgba(52,152,219,0.2)' : 'rgba(155,89,182,0.2)'; ?>; border-radius: 3px; font-size: 11px;">
                                        <?php echo $ipType; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($inFile): ?>
                                        <a href="?module=ip&action=delete&ip=<?php echo urlencode($ip); ?>"
                                           class="btn btn-danger"
                                           style="padding: 4px 8px; font-size: 11px; text-decoration: none;"
                                           onclick="return confirm('确定要从独立文件中删除这个IP吗？')">删除</a>
                                    <?php else: ?>
                                        <span style="color: #95a5a6; font-size: 11px;">来自UA</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 独立文件IP -->
            <div id="tab-file" class="tab-content">
                <div style="max-height: 400px; overflow-y: auto; background: rgba(255,255,255,0.9); border-radius: 6px; padding: 15px;">
                    <table class="table" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th style="width: 50%;">IP 地址</th>
                                <th style="width: 20%;">类型</th>
                                <th style="width: 30%;">操作</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ip_lists['from_file'] as $ip):
                                $ipType = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'IPv4' : 'IPv6';
                            ?>
                            <tr>
                                <td style="font-family: monospace; font-size: 12px; font-weight: 600;">
                                    <?php echo htmlspecialchars($ip); ?>
                                </td>
                                <td style="font-size: 12px;">
                                    <span style="padding: 2px 6px; background: <?php echo $ipType === 'IPv4' ? 'rgba(52,152,219,0.2)' : 'rgba(155,89,182,0.2)'; ?>; border-radius: 3px; font-size: 11px;">
                                        <?php echo $ipType; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?module=ip&action=delete&ip=<?php echo urlencode($ip); ?>"
                                       class="btn btn-danger"
                                       style="padding: 4px 8px; font-size: 11px; text-decoration: none;"
                                       onclick="return confirm('确定要删除这个IP吗？')">删除</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 来自UA的IP -->
            <div id="tab-ua" class="tab-content">
                <div style="max-height: 400px; overflow-y: auto; background: rgba(255,255,255,0.9); border-radius: 6px; padding: 15px;">
                    <table class="table" style="font-size: 12px;">
                        <thead>
                            <tr>
                                <th style="width: 50%;">IP 地址</th>
                                <th style="width: 20%;">类型</th>
                                <th style="width: 30%;">状态</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ip_lists['from_ua'] as $ip):
                                $ipType = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ? 'IPv4' : 'IPv6';
                            ?>
                            <tr>
                                <td style="font-family: monospace; font-size: 12px; font-weight: 600;">
                                    <?php echo htmlspecialchars($ip); ?>
                                </td>
                                <td style="font-size: 12px;">
                                    <span style="padding: 2px 6px; background: <?php echo $ipType === 'IPv4' ? 'rgba(52,152,219,0.2)' : 'rgba(155,89,182,0.2)'; ?>; border-radius: 3px; font-size: 11px;">
                                        <?php echo $ipType; ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="color: #e74c3c; font-size: 11px;">来自UA黑名单文件（只读）</span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <h3>📭 暂无IP黑名单</h3>
                <p>您可以通过上传文件、手动添加或从UA文件同步来建立黑名单。</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.tab-btn.active {
    background: #007bff !important;
}

.tab-btn:hover {
    background: #0056b3 !important;
}


</style>

<script>
// 标签页切换
function showTab(tabName) {
    // 隐藏所有标签内容
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // 移除所有按钮的active状态
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // 显示选中的标签内容
    document.getElementById('tab-' + tabName).classList.add('active');
    
    // 激活对应的按钮
    event.target.classList.add('active');
}

// 导出IP列表
function exportIPList() {
    const ips = <?php echo json_encode($ip_lists['all']); ?>;
    const content = ips.join('\n');
    const blob = new Blob([content], { type: 'text/plain' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'ip_blacklist_' + new Date().toISOString().slice(0, 10) + '.txt';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    showToast('IP黑名单已导出');
}

// 搜索功能
function filterIPList() {
    const searchTerm = document.getElementById('ip-search').value.toLowerCase();
    const items = document.querySelectorAll('.ip-item');
    
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        if (text.includes(searchTerm)) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}
</script>
