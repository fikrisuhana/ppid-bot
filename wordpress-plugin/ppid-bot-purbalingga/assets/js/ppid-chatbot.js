/**
 * PPID Bot Purbalingga - Frontend Client Controller
 */
(function() {
    'use strict';

    class PPIDBotPurbalingga {
        constructor() {
            this.config = window.ppid_bot_config || {
                apiUrl: 'http://localhost:5000/ask',
                templatesUrl: 'http://localhost:5000/templates',
                helpdeskPhone: '6281234567890',
                portalUrl: 'https://ppid.purbalinggakab.go.id'
            };

            this.isOpen = false;
            this.initElements();
            this.bindEvents();
            this.loadTemplates();
        }

        initElements() {
            this.container = document.getElementById('ppid-chatbot-container');
            this.toggleBtn = document.getElementById('ppidChatbotToggle');
            this.toggleWrapper = document.getElementById('ppidToggleContainer');
            this.chatWindow = document.getElementById('ppid-floating-chatbot');
            this.closeBtn = document.getElementById('ppidCloseBtn');
            this.body = document.getElementById('ppidChatBody');
            this.input = document.getElementById('ppidChatInput');
            this.sendBtn = document.getElementById('ppidSendBtn');
            this.typing = document.getElementById('ppidTyping');
            this.accordion = document.getElementById('ppidQuickAccordion');
            this.accordionHeader = document.getElementById('ppidAccordionHeader');
            this.accordionContent = document.getElementById('ppidAccordionContent');
        }

        bindEvents() {
            if (this.toggleBtn) {
                this.toggleBtn.addEventListener('click', () => this.toggleChat());
            }
            if (this.closeBtn) {
                this.closeBtn.addEventListener('click', () => this.closeChat());
            }
            if (this.sendBtn) {
                this.sendBtn.addEventListener('click', () => this.sendMessage());
            }
            if (this.input) {
                this.input.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') this.sendMessage();
                });
            }
            if (this.accordionHeader) {
                this.accordionHeader.addEventListener('click', () => {
                    this.accordion.classList.toggle('expanded');
                });
            }
        }

        toggleChat() {
            this.isOpen ? this.closeChat() : this.openChat();
        }

        openChat() {
            this.isOpen = true;
            this.chatWindow.classList.add('open');
            if (this.toggleWrapper) this.toggleWrapper.classList.add('hidden');
            setTimeout(() => this.input.focus(), 300);
        }

        closeChat() {
            this.isOpen = false;
            this.chatWindow.classList.remove('open');
            if (this.toggleWrapper) this.toggleWrapper.classList.remove('hidden');
        }

        async loadTemplates() {
            try {
                const res = await fetch(this.config.templatesUrl);
                if (res.ok) {
                    const data = await res.json();
                    if (data.templates && data.templates.length > 0) {
                        this.renderTemplates(data.templates);
                    }
                }
            } catch (err) {
                console.log('Using default local templates');
                this.renderTemplates([
                    "Bagaimana cara mengajukan permohonan informasi?",
                    "Berapa biaya permohonan informasi publik?",
                    "Berapa lama proses layanan informasi?",
                    "Bagaimana alur pengajuan keberatan informasi?"
                ]);
            }
        }

        renderTemplates(templates) {
            if (!this.accordionContent) return;
            this.accordionContent.innerHTML = '';
            templates.forEach(text => {
                const btn = document.createElement('button');
                btn.className = 'ppid-quick-btn';
                btn.textContent = text;
                btn.addEventListener('click', () => {
                    this.accordion.classList.remove('expanded');
                    this.sendMessage(text);
                });
                this.accordionContent.appendChild(btn);
            });
        }

        addMessage(text, sender = 'bot', links = []) {
            const msgDiv = document.createElement('div');
            msgDiv.className = `ppid-message ${sender}`;

            // Normalize literal 
 / 
 to real newlines, then parse markdown
            let formattedText = String(text || '')
                .replace(/\r\n/g, '
')
                .replace(/\n/g, '
')
                .replace(/
/g, '
')
                .replace(//g, '
')
                .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.*?)\*/g, '<em>$1</em>')
                .replace(/
/g, '<br>');

            let html = `<div class="ppid-bubble">${formattedText}</div>`;

            if (links && links.length > 0) {
                html += '<div class="ppid-message-links">';
                links.forEach(l => {
                    html += `<a href="${l.url}" target="_blank" class="ppid-link-badge">🔗 ${l.title}</a>`;
                });
                html += '</div>';
            }

            msgDiv.innerHTML = html;
            this.body.appendChild(msgDiv);
            this.body.scrollTop = this.body.scrollHeight;
        }

        showTyping() {
            this.typing.classList.add('active');
            this.body.scrollTop = this.body.scrollHeight;
        }

        hideTyping() {
            this.typing.classList.remove('active');
        }

        async sendMessage(customText = null) {
            const query = customText || this.input.value.trim();
            if (!query) return;

            if (!customText) this.input.value = '';
            this.addMessage(query, 'user');
            this.showTyping();

            try {
                const headers = { 'Content-Type': 'application/json' };
                if (this.config.nonce) {
                    headers['X-WP-Nonce'] = this.config.nonce;
                }

                const response = await fetch(this.config.apiUrl, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({ question: query, env: 'ppid' })
                });

                this.hideTyping();

                if (!response.ok) {
                    throw new Error('Gagal menghubungi server chatbot');
                }

                const data = await response.json();
                this.addMessage(data.answer, 'bot', data.links || []);

            } catch (error) {
                this.hideTyping();
                this.addMessage(
                    "Mohon maaf, koneksi ke server sedang mengalami gangguan. Silakan coba kembali sesaat lagi.",
                    'bot'
                );
            }
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.ppidBotInstance = new PPIDBotPurbalingga();
    });
})();
