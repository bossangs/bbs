# bk.chuhuo.top 社区论坛

这是基于 Xiuno BBS 4.0.4 改造的自有品牌社区论坛。界面采用 Linux.do/Discourse 风格的浅色话题流，功能参考现代技术社区常见交互；不复制其他网站的商标、私有数据或原样文案。

## 主要功能

- **访问控制**：访客默认可浏览公开板块和公开主题；受限板块按 Xiuno 用户组与板块访问矩阵过滤。发帖、回复、私信、收藏及其他个人操作需登录；登录、注册和找回密码页面保持公开。管理员可在后台调整全站访客门槛和逐板块可见性。
- **社区发现**：精选/发现、站内搜索、RSS，以及原有板块、主题、回复和个人中心。
- **互动与沟通**：点赞、感谢、反对、收藏、关注用户、通知、站内私信和举报；管理员可按需关闭各模块。
- **评论抽奖**：管理员可按主题创建活动，回复者自动进入候选池；开奖操作写入审计记录。是否允许普通会员开奖、默认周期和中奖人数均可配置。
- **账号邮件验证**：注册/找回密码验证码经站点 SMTP 发出；后台可配置 SMTP 加密方式并主动发送测试邮件。
- **主题和参数**：可在社区设置中配置页面、卡片、导航、强调色，首页主题数及抽奖默认值。
- **安全与兼容**：全站 POST CSRF 校验；上传目录禁用 PHP 执行；安装 SQL 不再删除已有表；PDO MySQL 安装路径及 host:port 支持；关闭伪静态也可运行。

## 功能范围

1.0.0 是论坛与社区功能版，依据公开可见的社区交互实现自有功能；不是对 NodeSeek 登录后私有页面或后台的逐项/逐像素复刻。当前版本不包含独立商城、游戏大厅、域名市场或真实支付/交易网关，也不含任何外站账号数据。未列出的业务系统不应视为已实现。

## 运行要求

- PHP **7.0–8.4**；必须启用 `PDO MySQL`、`mbstring`、`json`、`zlib`。
- MySQL 或 MariaDB（建议 InnoDB）。
- Docker Compose 部署默认使用 PHP 8.3、MariaDB 11.4、Nginx。
- 可部署于 Linux/Apache、Nginx/PHP-FPM、普通虚拟主机及 Windows/IIS。伪静态默认关闭；IIS 无 URL Rewrite 模块时可使用 `web.no-rewrite.config`。

## 安装与配置

- PHP 主机和 Windows/IIS：按 [`DEPLOYMENT.md`](DEPLOYMENT.md) 的“普通 PHP 主机”或“IIS”章节操作。
- Docker：复制 `.env.example` 为 `.env`，先设置强随机数据库口令，再运行 `docker compose up -d --build`。默认 Web 端口只绑定本机 `127.0.0.1:8080`；首次访问 `/install/` 完成安装。详细步骤见 [`DEPLOYMENT.md`](DEPLOYMENT.md)。
- 安装完成后登录 `/admin/`，打开“社区设置”配置登录门槛、模块开关、板块访问、主题色和抽奖默认值。SMTP 配置在后台基础设置中。
- 现有 Xiuno 4.0.4 站点迁移前先完整备份；扩展表迁移脚本见 `install/community_upgrade.sql`，详情见部署指南。

## 本地预览

`preview/index.html` 是不连接线上站点的静态交互预览。访客可看到公开板块和公开帖子，示例沙盒板块为受限内容；添加 `?preview=member` 查看登录会员界面，添加 `?preview=admin` 查看社区设置演示。预览中的登录、帖子和互动数据均为虚构的本地示例。

截图：

- 访客桌面：[`preview/screenshots/linuxdo-guest-desktop.png`](preview/screenshots/linuxdo-guest-desktop.png)
- 登录会员桌面：[`preview/screenshots/linuxdo-member-desktop.png`](preview/screenshots/linuxdo-member-desktop.png)
- 手机访客：[`preview/screenshots/linuxdo-guest-mobile.png`](preview/screenshots/linuxdo-guest-mobile.png)
- 手机会员：[`preview/screenshots/linuxdo-member-mobile.png`](preview/screenshots/linuxdo-member-mobile.png)
- 管理设置：[`preview/screenshots/linuxdo-admin-desktop.png`](preview/screenshots/linuxdo-admin-desktop.png)

## 验收记录

发布候选已在沙箱使用 PHP 8.4 与本机 MariaDB 完成全新安装、建表、首页访问、CSRF 表单校验、本地 SMTP 验证码捕获、邮箱验证注册，以及 PHP 7.0 启动已安装站点的集成测试。全项目 99 个 PHP 文件分别通过 PHP 7.0 与 PHP 8.4 语法检查；这不是对任意插件、托管环境或所有业务边界的兼容性保证。

## 许可证

保留原 Xiuno BBS 项目的 MIT 许可证与版权说明，见 [`LICENSE.txt`](LICENSE.txt)。
