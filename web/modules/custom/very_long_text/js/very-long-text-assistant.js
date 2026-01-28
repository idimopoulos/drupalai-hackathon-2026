(function (Drupal, drupalSettings, once) {
  'use strict';

  /**
   * Minimal wiring: pass current textarea content as context to the AI assistant.
   * Let DeepChat handle its own initialization and message rendering.
   */
  Drupal.behaviors.vltAssistantWiring = {
    attach(context) {
      const instances = (drupalSettings.very_long_text && drupalSettings.very_long_text.instances) || [];

      instances.forEach((cfg) => {
        const wrapper = context.querySelector('#' + cfg.wrapperId);
        if (!wrapper) return;

        once('vlt-assistant-wire', wrapper).forEach(() => {
          const textarea = wrapper.querySelector('textarea');
          const deepchatEl = wrapper.querySelector('deep-chat');

          if (!textarea || !deepchatEl) return;

          // Force deep-chat to fill container height (input stays at bottom).
          deepchatEl.style.height = '100%';

          // Inject styles into shadow DOM to push input to bottom.
          const injectStyles = () => {
            const shadow = deepchatEl.shadowRoot;
            if (!shadow) return;

            const chatView = shadow.getElementById('chat-view');
            if (chatView) {
              chatView.style.display = 'flex';
              chatView.style.flexDirection = 'column';
              chatView.style.height = '100%';
            }

            const messages = shadow.getElementById('messages');
            if (messages) {
              messages.style.flex = '1 1 auto';
              messages.style.overflowY = 'auto';
            }

            const input = shadow.getElementById('input');
            if (input) {
              input.style.marginTop = 'auto';
            }
          };

          // Try immediately and after a short delay (in case shadow DOM isn't ready).
          injectStyles();
          setTimeout(injectStyles, 100);

          // Inject current textarea value into the user message for AI context.
          deepchatEl.requestInterceptor = (request) => {
            const markdown = textarea.value || '';
            if (markdown && request.body && request.body.messages) {
              // Find the last user message and prepend the markdown context.
              const messages = request.body.messages;
              for (let i = messages.length - 1; i >= 0; i--) {
                if (messages[i].role === 'user') {
                  const originalText = messages[i].text || '';
                  messages[i].text = `[Current markdown content in the field]:\n\`\`\`\n${markdown}\n\`\`\`\n\n[User question]: ${originalText}`;
                  break;
                }
              }
            }
            return request;
          };
        });
      });
    }
  };

})(Drupal, drupalSettings, once);
