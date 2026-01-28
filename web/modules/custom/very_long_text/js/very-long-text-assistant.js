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

          // Handle markdown updates from the agent.
          deepchatEl.responseInterceptor = (response) => {
            const content = response.html || response.text || '';
            const marker = 'UPDATED_MARKDOWN:';
            const idx = content.indexOf(marker);

            if (idx !== -1) {
              // Extract everything after the marker.
              let afterMarker = content.substring(idx + marker.length);

              // Parse as HTML to find the code block.
              const temp = document.createElement('div');
              temp.innerHTML = afterMarker;

              // Look for code block (could be <pre><code>, <code>, or <pre>).
              const codeEl = temp.querySelector('pre code') || temp.querySelector('code') || temp.querySelector('pre');

              if (codeEl) {
                // Get text content from the code block (preserves markdown).
                const markdown = codeEl.textContent || codeEl.innerText || '';
                if (markdown.trim()) {
                  textarea.value = markdown.trim();
                  textarea.dispatchEvent(new Event('input', { bubbles: true }));
                  textarea.dispatchEvent(new Event('change', { bubbles: true }));
                  // Trigger keyup to fire the AJAX preview update.
                  textarea.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
                }
              }

              // Strip the markdown from the chat response - only show explanation.
              const explanation = content.substring(0, idx).trim();
              if (response.html) {
                response.html = explanation || 'Content updated.';
              }
              if (response.text) {
                response.text = explanation || 'Content updated.';
              }
            }
            return response;
          };
        });
      });
    }
  };

})(Drupal, drupalSettings, once);
