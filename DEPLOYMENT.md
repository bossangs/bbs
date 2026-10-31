# 安装与部署指南

## 1. 环境要求

- PHP 7.0–8.4。
- 必需扩展：`PDO MySQL`、`mbstring`、`json`、`zlib`。安装器也会显示 OpenSSL、cURL、Zip、Fileinfo 等建议扩展状态。
- MySQL 或 MariaDB，建议 InnoDB；数据库账号只授予本论坛数据库所需权限。
- 站点运行目录仅需让 PHP 进程写入 `conf/`、`log/`、`tmp/`、`upload/`、`plugin/`。不要把整个网站目录设为 `777`。
- 默认关闭伪静态（`url_rewrite_on=0`），使用 Xiuno 的 `index.php?route` URL，可在普通虚拟主机和无 rewrite 环境运行。

## 2. 普通 PHP 主机 / 虚拟主机

1. 在主机面板创建 MySQL/MariaDB 数据库和专用数据库用户，并授予该数据库的建表、增删改查权限。不要用数据库 `root` 账号连接论坛。
2. 解压完整安装包，将文件上传到域名根目录或子目录。保留 `install/` 直到安装完成。
3. 在面板启用 PHP 7.0–8.4 和必需扩展。将 `conf/`、`log/`、`tmp/`、`upload/`、`plugin/` 设为 PHP 运行用户可写；用“目录所有者”或最小所需权限，不要使用全站 `777`。
4. 浏览 `https://你的域名/install/`，按环境检查提示继续。数据库主机可填写 `localhost`、`127.0.0.1` 或 `主机:端口`；IPv6 可用 `[IPv6地址]:端口`。填写数据库名、专用用户、密码、表前缀和初始管理员信息。
5. 安装器会使用 PDO MySQL 建表并写入 `conf/conf.php`。管理员密码和数据库密码只在自己的服务器配置，不要提交到代码仓库。
6. 安装成功后先退出并重新登录后台，确认首页、管理员页和附件目录可用。随后建议移除服务器上的 `install/` 目录；若保留，请确保安装锁文件存在且该目录不能被公网读取 SQL 或其他安装文件。
7. 后台“社区设置”默认允许访客查看公开板块和公开主题；设为受限的板块按用户组访问矩阵过滤。发帖、回复、私信等账户操作要求登录。若需要全站登录墙，可在后台开启；也可逐板块调整公开/受限状态。
8. 在后台配置 SMTP 后，使用明确填写的测试收件地址验证发信。注册/找回密码验证码依赖可用 SMTP；测试邮件按钮会真实发送邮件。

## 3. Docker Compose

在 Linux/macOS/Windows 的 Docker 主机上：

```sh
cp .env.example .env
```

编辑 `.env`，将 `DB_ROOT_PASSWORD` 与 `DB_PASSWORD` 都替换为不同的长随机密码。保留 `WEB_BIND=127.0.0.1`，不要把数据库端口或应用端口直接暴露到公网。然后启动：

```sh
docker compose up -d --build
docker compose ps
```

默认页面只绑定 `127.0.0.1:8080`。首次安装访问 `http://127.0.0.1:8080/install/`，在数据库页填写：

- 数据库主机：`db`
- 端口：`3306`
- 数据库名：`.env` 中的 `DB_DATABASE`（默认 `xiuno`）
- 用户：`.env` 中的 `DB_USER`（默认 `xiuno`）
- 密码：`.env` 中的 `DB_PASSWORD`

完成后设置管理员账号并登录确认。Compose 使用持久化卷 `db-data` 保存数据库；重新构建镜像不会删除该卷。查看日志：

```sh
docker compose logs --tail=100 web app db
```

公开服务建议在前面配置 HTTPS 反向代理到本机 `127.0.0.1:8080`。`.env` 不得公开，也不要把 `.env`、`conf/conf.php`、数据库转储或 `upload/` 运行时用户文件提交到 Git。

## 4. Apache、Nginx 与无伪静态

- Apache 可使用根目录 `.htaccess` 和敏感目录中的子目录规则；如主机不允许 `.htaccess`，请在面板/Nginx 配置中阻止访问 `conf/`、`log/`、`tmp/`、`tool/`、`data/`、`plugin/`，并禁止 `upload/` 执行 PHP。
- Nginx 可参考 `docker/nginx/default.conf` 的敏感目录、安装目录及上传脚本限制。PHP 路由由 `index.php` 处理。
- 不需要开启 URL Rewrite。若管理员主动开启伪静态，需同时配置相应 Web 服务器规则；否则保持默认关闭。

## 5. Windows / IIS

1. 为 IIS 配置 PHP FastCGI，并启用 `pdo_mysql`、`mbstring`、`json`、`zlib`。在 IIS 应用程序池身份上，仅为 `conf`、`log`、`tmp`、`upload`、`plugin` 授予修改权限。
2. 默认包内 `web.config` 使用 IIS URL Rewrite 模块提供 front-controller 规则。若已安装该模块，可直接使用。
3. 如果服务器**没有** URL Rewrite 模块，将 `web.no-rewrite.config` 复制为根目录 `web.config`（先备份原文件），并保持安装配置里的伪静态关闭。Xiuno 会生成 `index.php?route` 链接，不依赖 rewrite。
4. `upload/web.config` 会把上传目录限制为静态读取。成功安装后移除 `install/` 目录；若需保留，使用 IIS 请求过滤/授权限制安装 SQL 与其他文件。

## 6. 从已有 Xiuno BBS 4.0.4 升级

1. 先对网站文件和数据库做完整备份，并在测试副本上演练；升级前确认 PHP、MySQL、权限和插件兼容情况。
2. 上传改造版文件时，不要覆盖已有 `conf/conf.php`、数据库、上传附件或自定义插件。保存好旧版文件和数据库备份。
3. 新增社区功能表、邀请码表及示例板块的迁移脚本为 `install/community_upgrade.sql`。它不会删除或覆盖已有主题、用户或已占用的板块记录；会有意将游客组设为可读公开板块、不可发主题/回复。示例板块使用 `INSERT IGNORE`，ID 已占用时保留原记录。默认表前缀为 `bbs_`；若原站前缀不同，先将脚本中的表前缀替换为实际前缀。
4. 使用数据库管理工具在**正确的现有数据库**中运行该迁移脚本。迁移前确认备份可恢复；不要在新安装中重复手动执行它。
5. 登录后台检查论坛板块、用户组访问矩阵、SMTP 和社区设置。新装默认开放公开板块给游客；若需访客看不到任何板块，可在后台开启全站登录门槛，再按组配置允许的板块。

## 7. 邀请码与回复邮件

- 邀请码默认不强制。管理员登录 `/admin/` →“社区设置”生成邀请码，可设可用次数（1–1000）和有效天数（0 表示不过期），也可查看使用状态或撤销。生成提示中的明文只显示一次；请在离开页面前复制。数据库只保存邀请码哈希。
- 同页的“注册必须输入邀请码”开关控制是否要求新用户输入邀请码。现有用户和后台创建用户不受影响。
- “主题作者收到新回复邮件”开关默认关闭。开启后，新回复保存成功后会 best-effort 发给主题作者；跳过本人回复和当前无权阅读该板块的作者。SMTP 发送最长等待约 5 秒；失败记录在站点日志，评论仍保留。
- 评论通知使用后台“设置 → SMTP”配置。若在测试环境验证邮件，请把 SMTP 指向仅本机监听的捕获服务；管理页的 SMTP 测试按钮会向指定地址真实发信，不要用真实收件人做测试。

## 8. 常见问题

- **安装器提示版本或扩展不满足**：确认网站实际运行的 PHP-FPM/FastCGI 版本和扩展，与命令行 PHP 的配置可能不同。
- **安装后目录不可写**：只给 `conf/`、`log/`、`tmp/`、`upload/`、`plugin/` 的 Web 运行用户所需写权限。
- **SMTP 验证失败**：确认 SMTP 主机、端口、账号、密码和 SSL/STARTTLS 方式；验证码请求会以 JSON 返回失败原因，后台测试邮件可单独验证连通性。
- **IIS 报 rewrite 配置错误**：安装 IIS URL Rewrite，或按上文将 `web.no-rewrite.config` 设为根 `web.config` 并保持伪静态关闭。
- **升级后社区表不存在**：检查是否在正确数据库执行 `install/community_upgrade.sql`，并确认该文件的表前缀与原站一致。
