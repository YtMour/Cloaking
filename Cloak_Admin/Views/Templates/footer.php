        </main>

        <footer style="text-align: center; padding: 20px; color: rgba(255,255,255,0.7); font-size: 0.9rem;">
            <p>© 2025 Cloak System - 智能流量过滤系统</p>
        </footer>
    </div>

    <script>
        // 通用JavaScript功能
        
        // 确认删除操作
        function confirmDelete(message = '确定要删除吗？') {
            return confirm(message);
        }
        
        // 确认批量操作
        function confirmBatchAction(action) {
            const selected = document.querySelectorAll('input[name="selected_items[]"]:checked');
            if (selected.length === 0) {
                alert('请先选择要操作的项目');
                return false;
            }
            return confirm(`确定要对选中的 ${selected.length} 个项目执行"${action}"操作吗？`);
        }
        
        // 全选/取消全选
        function toggleSelectAll(checkbox) {
            const checkboxes = document.querySelectorAll('input[name="selected_items[]"]');
            const batchButtons = document.querySelectorAll('.batch-btn');
            const selectionCount = document.getElementById('selection-count');
            
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
            
            updateBatchButtons();
        }
        
        // 更新批量操作按钮状态
        function updateBatchButtons() {
            const selected = document.querySelectorAll('input[name="selected_items[]"]:checked');
            const batchButtons = document.querySelectorAll('.batch-btn');
            const selectionCount = document.getElementById('selection-count');
            
            const count = selected.length;
            const hasSelection = count > 0;
            
            batchButtons.forEach(btn => {
                btn.disabled = !hasSelection;
            });
            
            if (selectionCount) {
                if (count === 0) {
                    selectionCount.textContent = '未选择任何项目';
                } else {
                    selectionCount.textContent = `已选择 ${count} 个项目`;
                }
            }
            
            // 更新全选复选框状态
            const selectAllCheckbox = document.getElementById('select-all');
            if (selectAllCheckbox) {
                const totalCheckboxes = document.querySelectorAll('input[name="selected_items[]"]').length;
                selectAllCheckbox.checked = count === totalCheckboxes && count > 0;
                selectAllCheckbox.indeterminate = count > 0 && count < totalCheckboxes;
            }
        }
        
        // 监听复选框变化
        document.addEventListener('change', function(e) {
            if (e.target.name === 'selected_items[]') {
                updateBatchButtons();
            }
        });
        
        // 设置批量操作类型
        function setBatchType(type) {
            const batchTypeInput = document.getElementById('batch_type');
            if (batchTypeInput) {
                batchTypeInput.value = type;
            }
        }
        
        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            // 初始化批量操作按钮状态
            updateBatchButtons();
            
            // 为批量操作按钮添加点击事件
            document.querySelectorAll('.batch-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const type = this.getAttribute('name') === 'batch_add_to_blacklist' ? this.value : '';
                    setBatchType(type);
                });
            });
            
            // 自动隐藏消息提示
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-10px)';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
            
            // 表格行点击选择
            document.querySelectorAll('.table tr').forEach(row => {
                const checkbox = row.querySelector('input[name="selected_items[]"]');
                if (checkbox) {
                    row.addEventListener('click', function(e) {
                        if (e.target.type !== 'checkbox' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A') {
                            checkbox.checked = !checkbox.checked;
                            updateBatchButtons();
                        }
                    });
                }
            });
            
            // 搜索功能
            const searchInput = document.getElementById('search-input');
            if (searchInput) {
                let searchTimeout;
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        performSearch(this.value);
                    }, 500);
                });
            }
            
            // 实时刷新功能
            if (typeof enableAutoRefresh !== 'undefined' && enableAutoRefresh) {
                setInterval(refreshStats, 30000); // 每30秒刷新一次统计
            }
        });
        
        // 执行搜索
        function performSearch(keyword) {
            if (keyword.length < 2) return;
            
            // 这里可以添加AJAX搜索逻辑
            console.log('搜索关键词:', keyword);
        }
        
        // 刷新统计信息
        function refreshStats() {
            fetch('Cloak_admin.php?module=monitor&action=ajax_stats')
                .then(response => response.json())
                .then(data => {
                    updateStatsDisplay(data);
                })
                .catch(error => {
                    console.error('刷新统计失败:', error);
                });
        }
        
        // 更新统计显示
        function updateStatsDisplay(stats) {
            // 更新各种统计数据的显示
            const elements = {
                'total-requests': stats.total_requests,
                'bot-requests': stats.bot_requests,
                'real-requests': stats.real_requests,
                'unique-ips': stats.unique_ips
            };
            
            Object.entries(elements).forEach(([id, value]) => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value;
                }
            });
        }
        
        // 复制到剪贴板
        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('已复制到剪贴板');
                });
            } else {
                // 兼容旧浏览器
                const textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('已复制到剪贴板');
            }
        }
        
        // 显示提示消息
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type}`;
            toast.style.position = 'fixed';
            toast.style.top = '20px';
            toast.style.right = '20px';
            toast.style.zIndex = '9999';
            toast.style.minWidth = '200px';
            toast.textContent = message;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }
        
        // 格式化文件大小
        function formatFileSize(bytes) {
            const units = ['B', 'KB', 'MB', 'GB'];
            let size = bytes;
            let unitIndex = 0;
            
            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }
            
            return Math.round(size * 100) / 100 + ' ' + units[unitIndex];
        }
        
        // 格式化时间差
        function formatTimeDiff(timestamp) {
            const now = Date.now() / 1000;
            const diff = now - timestamp;
            
            if (diff < 60) {
                return Math.floor(diff) + '秒前';
            } else if (diff < 3600) {
                return Math.floor(diff / 60) + '分钟前';
            } else if (diff < 86400) {
                return Math.floor(diff / 3600) + '小时前';
            } else {
                return Math.floor(diff / 86400) + '天前';
            }
        }
        
        // 导出功能
        function exportData(format, filters = {}) {
            const params = new URLSearchParams({
                module: 'monitor',
                action: 'export',
                format: format,
                ...filters
            });
            
            window.open(`Cloak_admin.php?${params.toString()}`);
        }
        
        // 键盘快捷键
        document.addEventListener('keydown', function(e) {
            // Ctrl+A 全选
            if (e.ctrlKey && e.key === 'a' && e.target.tagName !== 'INPUT' && e.target.tagName !== 'TEXTAREA') {
                e.preventDefault();
                const selectAllCheckbox = document.getElementById('select-all');
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = true;
                    toggleSelectAll(selectAllCheckbox);
                }
            }
            
            // ESC 取消选择
            if (e.key === 'Escape') {
                const selectAllCheckbox = document.getElementById('select-all');
                if (selectAllCheckbox) {
                    selectAllCheckbox.checked = false;
                    toggleSelectAll(selectAllCheckbox);
                }
            }
        });
    </script>
</body>
</html>
