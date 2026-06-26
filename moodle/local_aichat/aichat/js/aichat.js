window.init_aichat_widget = function(Y, config) {
    if (document.getElementById('aichat-widget')) return; // Evitar inicialización duplicada

    // Inyectar estilos CSS
    const style = document.createElement('style');
    style.innerHTML = `
        #aichat-widget { position: fixed; bottom: 30px; right: 30px; z-index: 9999; font-family: inherit; }
        #aichat-btn { width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15); cursor: pointer; transition: transform 0.2s; font-size: 24px; }
        #aichat-btn:hover { transform: scale(1.05); }
        #aichat-window { display: none; position: absolute; bottom: 80px; right: 0; width: 350px; height: 500px; background: white; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.2); flex-direction: column; overflow: hidden; border: 1px solid #e5e7eb; }
        #aichat-header { padding: 16px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        #aichat-close { cursor: pointer; opacity: 0.8; font-size: 1.2rem; }
        #aichat-close:hover { opacity: 1; }
        #aichat-messages { flex: 1; padding: 16px; overflow-y: auto; background: #f9fafb; display: flex; flex-direction: column; gap: 12px; }
        .ai-msg { padding: 10px 14px; border-radius: 12px; font-size: 0.9rem; max-width: 85%; line-height: 1.4; word-wrap: break-word; }
        .ai-msg.bot { background: white; border: 1px solid #e5e7eb; align-self: flex-start; border-bottom-left-radius: 4px; }
        .ai-msg.user { align-self: flex-end; border-bottom-right-radius: 4px; }
        .ai-msg.system { background: transparent; color: #6b7280; font-size: 0.8rem; align-self: center; font-style: italic; }
        #aichat-input-area { padding: 16px; background: white; border-top: 1px solid #e5e7eb; display: flex; gap: 8px; }
        #aichat-input { flex: 1; padding: 10px; border: 1px solid #d1d5db; border-radius: 20px; outline: none; font-size: 0.9rem; }
        #aichat-input:focus { border-color: inherit; }
        #aichat-send { width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; padding: 0; }
        .typing-indicator { display: flex; gap: 4px; padding: 12px 14px; background: white; border: 1px solid #e5e7eb; border-radius: 12px; width: fit-content; align-self: flex-start; border-bottom-left-radius: 4px; }
        .typing-dot { width: 6px; height: 6px; background: #9ca3af; border-radius: 50%; animation: typing 1.4s infinite ease-in-out; }
        .typing-dot:nth-child(1) { animation-delay: -0.32s; }
        .typing-dot:nth-child(2) { animation-delay: -0.16s; }
        @keyframes typing { 0%, 80%, 100% { transform: scale(0); } 40% { transform: scale(1); } }
    `;
    document.head.appendChild(style);

    // Construir elementos del DOM
    const widget = document.createElement('div');
    widget.id = 'aichat-widget';
    widget.innerHTML = `
        <div id="aichat-window">
            <div id="aichat-header" class="bg-primary text-white">
                <span>Asistente de Curso IA</span>
                <span id="aichat-close">&times;</span>
            </div>
            <div id="aichat-messages">
                <div class="ai-msg bot">¡Hola! Soy tu tutor IA. Pregúntame lo que necesites sobre los recursos de este tema.</div>
            </div>
            <div id="aichat-input-area">
                <input type="text" id="aichat-input" class="form-control" placeholder="Escribe tu duda aquí..." autocomplete="off">
                <button id="aichat-send" class="btn btn-primary">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"></line><polygon points="22 2 15 22 11 13 2 9 22 2"></polygon></svg>
                </button>
            </div>
        </div>
        <div id="aichat-btn" class="bg-primary text-white">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
        </div>
    `;
    document.body.appendChild(widget);

    // Enlazar lógica de eventos
    const btn = document.getElementById('aichat-btn');
    const win = document.getElementById('aichat-window');
    const closeBtn = document.getElementById('aichat-close');
    const input = document.getElementById('aichat-input');
    const sendBtn = document.getElementById('aichat-send');
    const messages = document.getElementById('aichat-messages');

    btn.addEventListener('click', () => {
        win.style.display = win.style.display === 'flex' ? 'none' : 'flex';
        if (win.style.display === 'flex') input.focus();
    });

    closeBtn.addEventListener('click', () => win.style.display = 'none');

    const addMessage = (text, sender) => {
        const msg = document.createElement('div');
        msg.className = 'ai-msg ' + sender + (sender === 'user' ? ' bg-primary text-white' : '');
        // Formateo básico de negritas y saltos de línea
        msg.innerHTML = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>').replace(/\n/g, '<br>');
        messages.appendChild(msg);
        messages.scrollTop = messages.scrollHeight;
    };

    const showTyping = () => {
        const typing = document.createElement('div');
        typing.className = 'typing-indicator';
        typing.id = 'ai-typing';
        typing.innerHTML = '<div class="typing-dot"></div><div class="typing-dot"></div><div class="typing-dot"></div>';
        messages.appendChild(typing);
        messages.scrollTop = messages.scrollHeight;
    };

    const removeTyping = () => {
        const el = document.getElementById('ai-typing');
        if (el) el.remove();
    };

    const sendMessage = async () => {
        const text = input.value.trim();
        if (!text) return;
        
        input.value = '';
        addMessage(text, 'user');
        showTyping();

        // Detectar sección activa desde los parámetros de la URL
        const urlParams = new URLSearchParams(window.location.search);
        let section = urlParams.get('section') || '0';
        
        let res;
        try {
            const formData = new URLSearchParams();
            formData.append('courseid', config.courseid);
            formData.append('sesskey', config.sesskey);
            formData.append('section', section);
            formData.append('message', text);

            const rootUrl = new URL(config.wwwroot);
            const basePath = rootUrl.pathname === '/' ? '' : rootUrl.pathname;
            const fetchUrl = basePath + '/local/aichat/ajax.php';

            res = await fetch(fetchUrl, {
                method: 'POST',
                body: formData,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin'
            });

            const data = await res.json();
            
            removeTyping();
            
            if (data.error) {
                addMessage('Lo siento, ocurrió un error: ' + data.error, 'system');
            } else {
                addMessage(data.response, 'bot');
            }
        } catch (e) {
            removeTyping();
            if (typeof res !== 'undefined' && res) {
                res.text().then(text => {
                    addMessage('Error del servidor: ' + text.substring(0, 200), 'system');
                }).catch(err => {
                    addMessage('Error interno JS: ' + e.message, 'system');
                });
            } else {
                addMessage('Error interno JS: ' + e.message, 'system');
            }
        }
    };

    sendBtn.addEventListener('click', sendMessage);
    input.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });
};
