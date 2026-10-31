(function () {
  'use strict';
  var app = document.getElementById('app');
  if (!app) return;

  var panelHtml = '' +
    '<section class="invite-demo-panel" aria-labelledby="inviteDemoTitle">' +
      '<div class="invite-demo-heading"><div><h3 id="inviteDemoTitle">注册邀请码与回复邮件</h3><p>新增社区管理能力 · 下方操作仅改变本地预览状态</p></div><span class="invite-demo-tag">本地演示</span></div>' +
      '<label class="invite-demo-setting"><span><strong>注册必须输入邀请码</strong><small>开启后，注册提交和邮箱验证码申请都会校验邀请码。</small></span><input type="checkbox" data-demo-toggle="invite" aria-label="注册必须输入邀请码"></label>' +
      '<label class="invite-demo-setting"><span><strong>主题作者收到新回复邮件</strong><small>复用站点 SMTP；只通知有权阅读该主题的作者，不通知作者自己的回复。</small></span><input type="checkbox" data-demo-toggle="mail" aria-label="主题作者收到新回复邮件"></label>' +
      '<div class="invite-demo-create"><div class="invite-demo-fields">' +
        '<label><span>最多使用次数</span><input type="number" min="1" max="1000" value="1" data-demo-field="uses"></label>' +
        '<label><span>有效天数</span><input type="number" min="0" max="3650" value="0" data-demo-field="days"><small>0 表示不过期</small></label>' +
        '<label class="invite-note-field"><span>备注（可选）</span><input type="text" maxlength="120" placeholder="例如：社区内测" data-demo-field="note"></label>' +
      '</div><button type="button" class="btn btn-primary" data-invite-demo-action="generate">生成邀请码</button></div>' +
      '<div class="invite-demo-result" aria-live="polite"><strong>新码只显示一次</strong><code data-demo-code>尚未生成</code><span data-demo-feedback>生成后请立即复制保存；真实管理页会将邀请码明文只展示一次。</span></div>' +
      '<div class="invite-demo-table-wrap"><table class="invite-demo-table"><thead><tr><th>邀请码（仅前缀）</th><th>使用情况</th><th>有效期</th><th>状态</th><th>操作</th></tr></thead><tbody data-demo-invites><tr><td><code>DEMO-ONLY</code></td><td>演示数据</td><td>—</td><td><span class="invite-status">示例</span></td><td>—</td></tr></tbody></table></div>' +
      '<div class="invite-demo-note"><strong>邮件边界</strong><span>邮件发送失败不会撤销已保存的回复。请先配置后台 SMTP；本地预览不会发信，测试邮件应指向本机捕获器。</span></div>' +
    '</section>';

  function randomCode() {
    var bytes = new Uint8Array(16);
    if (window.crypto && window.crypto.getRandomValues) window.crypto.getRandomValues(bytes);
    else for (var i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    var hex = Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('').toUpperCase();
    return hex.match(/.{1,8}/g).join('-');
  }

  function addPanel() {
    if (document.body.getAttribute('data-view') !== 'admin') return;
    var card = app.querySelector('.admin-grid .content-card');
    if (!card || card.querySelector('.invite-demo-panel')) return;
    card.insertAdjacentHTML('beforeend', panelHtml);
    var panel = card.querySelector('.invite-demo-panel');
    panel.addEventListener('click', function (event) { event.stopPropagation(); });
    panel.querySelector('[data-invite-demo-action="generate"]').addEventListener('click', function () {
      var code = randomCode();
      var uses = Math.max(1, Math.min(1000, parseInt(panel.querySelector('[data-demo-field="uses"]').value, 10) || 1));
      var days = Math.max(0, Math.min(3650, parseInt(panel.querySelector('[data-demo-field="days"]').value, 10) || 0));
      var note = panel.querySelector('[data-demo-field="note"]').value.trim();
      panel.querySelector('[data-demo-code]').textContent = code;
      panel.querySelector('[data-demo-feedback]').textContent = '仅预览：限用 ' + uses + ' 次 · ' + (days ? days + ' 天有效' : '不过期') + (note ? ' · 备注：' + note : '') + ' · 不会保存到数据库。';
      var row = document.createElement('tr');
      var prefixCell = document.createElement('td');
      var prefix = document.createElement('code');
      prefix.textContent = code.slice(0, 8) + '…';
      prefixCell.appendChild(prefix);
      var usesCell = document.createElement('td');
      usesCell.textContent = '0 / ' + uses;
      var expiryCell = document.createElement('td');
      expiryCell.textContent = days ? days + ' 天' : '不过期';
      var statusCell = document.createElement('td');
      statusCell.innerHTML = '<span class="invite-status active">有效</span>';
      var actionCell = document.createElement('td');
      var revoke = document.createElement('button');
      revoke.type = 'button';
      revoke.className = 'invite-revoke';
      revoke.textContent = '撤销';
      revoke.addEventListener('click', function () {
        var status = row.querySelector('.invite-status');
        if (status) { status.textContent = '已撤销'; status.classList.remove('active'); }
        revoke.disabled = true;
        revoke.textContent = '已撤销';
        panel.querySelector('[data-demo-feedback]').textContent = '演示邀请码已撤销（仅本地状态）。';
      });
      actionCell.appendChild(revoke);
      row.appendChild(prefixCell);
      row.appendChild(usesCell);
      row.appendChild(expiryCell);
      row.appendChild(statusCell);
      row.appendChild(actionCell);
      panel.querySelector('[data-demo-invites]').prepend(row);
    });
    panel.querySelectorAll('[data-demo-toggle]').forEach(function (toggle) {
      toggle.addEventListener('change', function () {
        var name = toggle.getAttribute('data-demo-toggle') === 'invite' ? '邀请码强制校验' : '主题回复邮件通知';
        panel.querySelector('[data-demo-feedback]').textContent = name + (toggle.checked ? '：已开启（仅本地演示）。' : '：已关闭（仅本地演示）。');
      });
    });
  }

  var observer = new MutationObserver(addPanel);
  observer.observe(app, { childList: true, subtree: true });
  addPanel();
})();
