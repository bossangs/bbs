# 本地功能验收

## 快速测试

在 Linux 沙箱中分别运行邀请码模型与后台动作单元测试：

```sh
php7.0 tests/test_invite_helpers.php
php7.0 tests/test_invite_admin_action.php
php8.4 tests/test_invite_helpers.php
php8.4 tests/test_invite_admin_action.php
```

完整端到端测试命令为 `bash tests/community_feature_integration.sh`。它需要本机 MariaDB 的 socket 管理权限、PHP 7.0/8.4 CLI、Python 3、curl、OpenSSL 和 `sudo`。只在专用测试机或沙箱运行；不要在装有生产数据库的服务器上运行。

## 覆盖内容

端到端脚本在临时数据库/账号内完成 PHP 8.4 全新安装和社区迁移，检查游客公开内容与受限板块权限、邀请码邮箱验证码注册、次数耗尽后的重放拒绝、并发限次扣减、回复通知主题 URL、本人回复和无阅读权限收件人的邮件跳过，以及评论抽奖候选回归。测试结束时清理临时数据库、数据库账号和临时站点目录。

## 邮件安全边界

测试不读取保存的 SMTP 凭据，也不使用任何外部邮件服务。脚本为隔离站点临时写入空凭据的 SMTP 配置，并仅连接 `127.0.0.1:1025` 上的 `tests/fake_smtp.py` 捕获器；邮件内容保存到 `/tmp` 供断言，不会被发往真实收件人。不要把真实 SMTP 配置用于测试。
