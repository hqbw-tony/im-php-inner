(function () {
  'use strict';

  var state = {
    authToken: '',
    userInfo: null,
    agents: [],
    chats: [],
    agentUserId: 0,
    agentKeyword: '',
    customerKeyword: '',
    selectedChat: null,
    pendingFile: null,
    pollTimer: 0
  };

  var dom = {};

  function $(id) { return document.getElementById(id); }

  function parseJsonResponse(response, path) {
    return response.text().then(function (text) {
      try {
        return JSON.parse(text);
      } catch (error) {
        if (/<!doctype|<html/i.test(text)) {
          throw new Error(path + ' 接口返回了网页内容，请检查网关转发配置');
        }
        throw new Error(path + ' 接口返回的数据格式错误');
      }
    });
  }

  function request(path, data) {
    var body = new URLSearchParams();
    Object.keys(data || {}).forEach(function (key) {
      if (data[key] !== undefined && data[key] !== null) body.append(key, data[key]);
    });
    return fetch(path, {
      method: 'POST',
      headers: {
        'Authorization': state.authToken,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (response) { return parseJsonResponse(response, path); });
  }

  function login(token) {
    return fetch('/common/pub/login', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
      },
      body: new URLSearchParams({ token: token, terminal: 'web' }).toString(),
      credentials: 'same-origin'
    }).then(function (response) { return parseJsonResponse(response, '/common/pub/login'); });
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value).replace(/[&<>'"]/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char];
    });
  }

  function plainText(value) {
    var holder = document.createElement('div');
    holder.innerHTML = String(value == null ? '' : value)
      .replace(/<br\s*\/?\s*>/gi, '\n')
      .replace(/<\/(p|div|li|h[1-6])\s*>/gi, '\n');
    return (holder.textContent || '').replace(/\u00a0/g, ' ').replace(/[ \t]+\n/g, '\n').replace(/\n{3,}/g, '\n\n').trim();
  }

  function matchesKeyword(keyword, values) {
    var needle = String(keyword || '').trim().toLowerCase();
    if (!needle) return true;
    return values.some(function (value) {
      return String(value == null ? '' : value).toLowerCase().indexOf(needle) !== -1;
    });
  }

  function visibleAgents() {
    return state.agents.filter(function (agent) {
      return matchesKeyword(state.agentKeyword, [agent.displayName, agent.realname, agent.external_agent_id, agent.im_user_id]);
    });
  }

  function visibleChats() {
    return state.chats.filter(function (chat) {
      var customer = chat.customer || {};
      return matchesKeyword(state.customerKeyword, [customer.displayName, customer.realname, customer.external_user_id, customer.im_user_id]);
    });
  }

  function formatTime(value) {
    if (!value) return '';
    var date = new Date(Number(value));
    if (isNaN(date.getTime())) return '';
    return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0') + ' ' +
      String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
  }

  function formatConversationTime(value) {
    if (!value) return '';
    var date = new Date(Number(value));
    if (isNaN(date.getTime())) return '';
    return String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0') + ' ' +
      String(date.getHours()).padStart(2, '0') + ':' + String(date.getMinutes()).padStart(2, '0');
  }

  function avatar(user, extraClass) {
    var name = escapeHtml((user && (user.displayName || user.realname)) || '');
    var src = escapeHtml((user && user.avatar) || '/static/img/avatar.png');
    return '<img class="' + (extraClass || 'avatar') + '" src="' + src + '" alt="' + name + '" onerror="this.src=\'/static/img/avatar.png\'">';
  }

  function showToast(message) {
    dom.toast.textContent = message || '请求失败';
    dom.toast.hidden = false;
    window.clearTimeout(showToast.timer);
    showToast.timer = window.setTimeout(function () { dom.toast.hidden = true; }, 3600);
  }

  function setLoading(message) {
    dom.loginState.textContent = message;
    dom.loginState.hidden = false;
    dom.workspace.hidden = true;
  }

  function renderAgents() {
    dom.allAgents.classList.toggle('active', !state.agentUserId);
    var agents = visibleAgents();
    dom.agentEmpty.hidden = agents.length > 0;
    dom.agentList.innerHTML = agents.map(function (agent) {
      var active = Number(agent.im_user_id) === Number(state.agentUserId) ? ' active' : '';
      var unreadCustomers = Number(agent.unread_customer_count || 0);
      var unreadClass = unreadCustomers ? '' : ' empty';
      return '<button class="agent-item' + active + '" type="button" data-agent-id="' + Number(agent.im_user_id) + '">' +
        avatar(agent) + '<span class="agent-meta"><span class="agent-name">' + escapeHtml(agent.displayName || agent.realname || agent.external_agent_id || '') + '</span>' +
        '<span class="agent-sub"><span>' + Number(agent.session_count || 0) + ' 个会话</span><span class="agent-unread' + unreadClass + '">' + (unreadCustomers > 99 ? '99+' : unreadCustomers) + ' 个客户未读</span></span></span></button>';
    }).join('');
    Array.prototype.forEach.call(dom.agentList.querySelectorAll('[data-agent-id]'), function (button) {
      button.addEventListener('click', function () { selectAgent(Number(button.getAttribute('data-agent-id'))); });
    });
  }

  function messagePreview(message) {
    if (!message) return '暂无消息';
    var types = { image: '[图片]', file: '[文件]', video: '[视频]', voice: '[语音]', emoji: '[表情]' };
    return types[message.type] || plainText(message.content).replace(/\s+/g, ' ').trim() || '暂无消息';
  }

  function renderChats() {
    dom.conversationTitle.textContent = state.agentUserId ? '代理会话' : '全部会话';
    var chats = visibleChats();
    dom.conversationEmpty.hidden = chats.length > 0;
    dom.conversationEmpty.textContent = state.customerKeyword ? '暂无匹配客户' : '暂无会话';
    dom.conversationList.innerHTML = chats.map(function (chat) {
      var active = state.selectedChat && Number(state.selectedChat.session_id) === Number(chat.session_id) ? ' active' : '';
      var unread = Number(chat.unread || 0);
      return '<button class="conversation-item' + active + '" type="button" data-session-id="' + Number(chat.session_id) + '">' +
        avatar(chat.customer) + '<span class="conversation-main"><span class="conversation-name">' + escapeHtml(chat.customer.displayName) + '</span>' +
        '<span class="conversation-agent">代理：' + escapeHtml(chat.agent.displayName) + '</span>' +
        '<span class="conversation-last">' + escapeHtml(messagePreview(chat.last_message)) + '</span></span>' +
        '<span class="conversation-time">' + formatConversationTime(chat.last_send_time) + (unread ? '<b class="unread">' + (unread > 99 ? '99+' : unread) + '</b>' : '') + '</span></button>';
    }).join('');
    Array.prototype.forEach.call(dom.conversationList.querySelectorAll('[data-session-id]'), function (button) {
      button.addEventListener('click', function () { selectChat(Number(button.getAttribute('data-session-id'))); });
    });
  }

  function renderChatHeader() {
    if (!state.selectedChat) return;
    var chat = state.selectedChat;
    dom.chatHeader.innerHTML = '<div>' +
      '<div class="chat-header-name">' + escapeHtml(chat.customer.displayName) + '</div>' +
      '<div class="chat-header-meta">当前以代理 ' + escapeHtml(chat.agent.displayName) + ' 的身份回复</div></div>';
  }

  function messageBody(message) {
    if (message.type === 'image') {
      return '<img class="message-image" src="' + escapeHtml(message.content) + '" alt="图片">';
    }
    if (['file', 'video', 'voice'].indexOf(message.type) !== -1) {
      var href = message.download || message.content;
      return '<a class="message-file" href="' + escapeHtml(href) + '" target="_blank" rel="noopener">' + escapeHtml(message.fileName || '查看文件') + '</a>';
    }
    return escapeHtml(plainText(message.content));
  }

  function renderMessages(messages) {
    var chat = state.selectedChat;
    dom.messageList.innerHTML = messages.map(function (message) {
      var mine = Number(message.from_user) === Number(chat.agent.im_user_id);
      var sender = message.fromUser || (mine ? chat.agent : chat.customer);
      return '<div class="message-row' + (mine ? ' mine' : '') + '">' + avatar(sender, 'message-avatar') +
        '<div class="message-content"><span class="message-name">' + escapeHtml(sender.displayName || sender.realname || '') + '</span>' +
        '<div class="message-bubble">' + messageBody(message) + '</div>' +
        '<span class="message-time">' + formatTime(message.sendTime) + '</span></div></div>';
    }).join('');
    dom.messageList.scrollTop = dom.messageList.scrollHeight;
  }

  function loadAgents() {
    return request('/enterprise/im/getManagerAgentList', {}).then(function (response) {
      if (response.code !== 0) throw new Error(response.msg || '获取代理失败');
      state.agents = response.data || [];
      renderAgents();
    });
  }

  function loadChats() {
    return request('/enterprise/im/getManagerChatList', state.agentUserId ? { agent_user_id: state.agentUserId } : {}).then(function (response) {
      if (response.code !== 0) throw new Error(response.msg || '获取会话失败');
      state.chats = response.data || [];
      if (state.selectedChat) {
        var replacement = state.chats.filter(function (chat) { return Number(chat.session_id) === Number(state.selectedChat.session_id); })[0];
        state.selectedChat = replacement || null;
      }
      if (state.selectedChat && !visibleChats().some(function (chat) { return Number(chat.session_id) === Number(state.selectedChat.session_id); })) {
        state.selectedChat = null;
      }
      renderChats();
      if (!state.selectedChat) showEmptyChat();
    });
  }

  function loadMessages() {
    if (!state.selectedChat) return Promise.resolve();
    return request('/enterprise/im/getManagerMessageList', { session_id: state.selectedChat.session_id, limit: 60 }).then(function (response) {
      if (response.code !== 0) throw new Error(response.msg || '获取消息失败');
      renderMessages(response.data || []);
      return loadChats().then(loadAgents);
    });
  }

  function showEmptyChat() {
    dom.chatEmpty.hidden = false;
    dom.chatView.hidden = true;
  }

  function selectAgent(agentUserId) {
    state.agentUserId = agentUserId;
    state.selectedChat = null;
    renderAgents();
    showEmptyChat();
    loadChats().catch(function (error) { showToast(error.message); });
  }

  function selectChat(sessionId) {
    state.selectedChat = state.chats.filter(function (chat) { return Number(chat.session_id) === Number(sessionId); })[0] || null;
    if (!state.selectedChat) return;
    renderChats();
    dom.chatEmpty.hidden = true;
    dom.chatView.hidden = false;
    renderChatHeader();
    dom.messageList.innerHTML = '<div class="empty-state">正在加载消息...</div>';
    loadMessages().catch(function (error) { showToast(error.message); });
  }

  function searchAgents() {
    state.agentKeyword = dom.agentSearchInput.value.trim();
    renderAgents();
  }

  function searchCustomers() {
    state.customerKeyword = dom.customerSearchInput.value.trim();
    if (state.selectedChat && !visibleChats().some(function (chat) { return Number(chat.session_id) === Number(state.selectedChat.session_id); })) {
      state.selectedChat = null;
      showEmptyChat();
    }
    renderChats();
  }

  function clearPendingFile() {
    state.pendingFile = null;
    dom.uploadName.textContent = '';
    dom.uploadInput.value = '';
  }

  function createMessageId() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    return 'manager-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
  }

  function uploadFile(file) {
    if (!file) return;
    var formData = new FormData();
    formData.append('file', file);
    dom.uploadName.textContent = '正在上传 ' + file.name;
    fetch('/common/upload/uploadFile', {
      method: 'POST',
      headers: {
        'Authorization': state.authToken,
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: formData,
      credentials: 'same-origin'
    }).then(function (response) { return parseJsonResponse(response, '/common/upload/uploadFile'); }).then(function (response) {
      if (response.code !== 0) throw new Error(response.msg || '文件上传失败');
      var fileInfo = response.data || {};
      var typeMap = { 2: 'image', 4: 'video' };
      state.pendingFile = {
        id: createMessageId(),
        content: fileInfo.src,
        type: typeMap[fileInfo.cate] || 'file',
        file_name: fileInfo.name ? fileInfo.name + (fileInfo.ext ? '.' + fileInfo.ext : '') : file.name,
        file_size: fileInfo.size || file.size,
        file_cate: fileInfo.cate || 0
      };
      dom.uploadName.textContent = state.pendingFile.file_name;
    }).catch(function (error) {
      clearPendingFile();
      showToast(error.message);
    });
  }

  function sendManagerMessage(payload, retried) {
    return request('/enterprise/im/managerSendMessage', payload).then(function (response) {
      if (response.code !== 0) {
        var error = new Error(response.msg || '发送消息失败');
        error.isBusinessError = true;
        throw error;
      }
      return response;
    }).catch(function (error) {
      if (!retried && !error.isBusinessError && String(error.message || '').indexOf('接口返回') !== -1) {
        return sendManagerMessage(payload, true);
      }
      throw error;
    });
  }

  function sendMessage() {
    if (!state.selectedChat) return;
    var text = dom.messageInput.value.trim();
    var payload = state.pendingFile ? Object.assign({}, state.pendingFile) : { id: createMessageId(), type: 'text', content: text };
    if (!payload.content) return;
    payload.session_id = state.selectedChat.session_id;
    dom.sendButton.disabled = true;
    sendManagerMessage(payload, false).then(function (response) {
      if (response.code !== 0) throw new Error(response.msg || '消息发送失败');
      dom.messageInput.value = '';
      clearPendingFile();
      loadMessages().catch(function (error) {
        if (window.console && window.console.warn) {
          window.console.warn('消息已发送，但会话刷新失败：', error);
        }
      });
    }).catch(function (error) {
      showToast(error.message);
    }).finally(function () {
      dom.sendButton.disabled = false;
    });
  }

  function refreshCurrent() {
    loadAgents().then(loadChats).then(function () {
      if (state.selectedChat) return loadMessages();
    }).catch(function (error) { showToast(error.message); });
  }

  function bindEvents() {
    dom.allAgents.addEventListener('click', function () { selectAgent(0); });
    dom.agentSearchButton.addEventListener('click', searchAgents);
    dom.agentSearchInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchAgents();
      }
    });
    dom.customerSearchButton.addEventListener('click', searchCustomers);
    dom.customerSearchInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchCustomers();
      }
    });
    dom.refreshButton.addEventListener('click', refreshCurrent);
    dom.logoutButton.addEventListener('click', function () {
      window.sessionStorage.removeItem('third-manager-auth');
      window.location.assign('/manager.html');
    });
    dom.sendButton.addEventListener('click', sendMessage);
    dom.messageInput.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
        event.preventDefault();
        sendMessage();
      }
    });
    dom.uploadButton.addEventListener('click', function () { dom.uploadInput.click(); });
    dom.uploadInput.addEventListener('change', function () { uploadFile(dom.uploadInput.files[0]); });
  }

  function initializeDom() {
    dom.loginState = $('login-state');
    dom.workspace = $('workspace');
    dom.managerName = $('manager-name');
    dom.allAgents = $('all-agents');
    dom.agentSearchInput = $('agent-search-input');
    dom.agentSearchButton = $('agent-search-button');
    dom.agentList = $('agent-list');
    dom.agentEmpty = $('agent-empty');
    dom.conversationTitle = $('conversation-title');
    dom.customerSearchInput = $('customer-search-input');
    dom.customerSearchButton = $('customer-search-button');
    dom.conversationList = $('conversation-list');
    dom.conversationEmpty = $('conversation-empty');
    dom.chatEmpty = $('chat-empty');
    dom.chatView = $('chat-view');
    dom.chatHeader = $('chat-header');
    dom.messageList = $('message-list');
    dom.messageInput = $('message-input');
    dom.sendButton = $('send-button');
    dom.uploadButton = $('upload-button');
    dom.uploadInput = $('upload-input');
    dom.uploadName = $('upload-name');
    dom.refreshButton = $('refresh-button');
    dom.logoutButton = $('logout-button');
    dom.toast = $('toast');
  }

  function start() {
    initializeDom();
    bindEvents();
    var params = new URLSearchParams(window.location.search);
    var oneTimeToken = params.get('token');
    var saved = window.sessionStorage.getItem('third-manager-auth');
    var initial = oneTimeToken ? login(oneTimeToken) : Promise.resolve(saved ? JSON.parse(saved) : null);
    initial.then(function (response) {
      if (!response || response.code !== 0 || !response.data || !response.data.authToken) {
        throw new Error((response && response.msg) || '登录链接无效或已过期');
      }
      state.authToken = response.data.authToken;
      state.userInfo = response.data.userInfo || {};
      window.sessionStorage.setItem('third-manager-auth', JSON.stringify(response));
      if (oneTimeToken) window.history.replaceState({}, document.title, '/manager.html?embed=1&manager=1');
      dom.managerName.textContent = state.userInfo.displayName || state.userInfo.realname || '';
      dom.loginState.hidden = true;
      dom.workspace.hidden = false;
      return loadAgents().then(loadChats);
    }).then(function () {
      state.pollTimer = window.setInterval(function () {
        if (document.hidden) return;
        loadAgents().then(loadChats).then(function () {
          if (state.selectedChat) return loadMessages();
        }).catch(function () {});
      }, 10000);
    }).catch(function (error) {
      setLoading(error.message || '登录失败');
    });
  }

  document.addEventListener('DOMContentLoaded', start);
}());
