document.addEventListener('DOMContentLoaded', function() {
    try {
        document.querySelectorAll('.btn-toggle-members').forEach(btn => {
            const teamId = btn.dataset.teamId || btn.getAttribute('data-team-id');
            const membersId = 'members-' + teamId;
            btn.setAttribute('aria-controls', membersId);

            // Set initial icon based on aria-expanded or container visibility
            const expandedAttr = btn.getAttribute('aria-expanded');
            const containerEl = document.getElementById(membersId);
            const isExpandedInitial = expandedAttr === 'true' || (expandedAttr === null && containerEl && !containerEl.classList.contains('collapsed'));

            btn.setAttribute('aria-expanded', isExpandedInitial ? 'true' : 'false');
            btn.textContent = isExpandedInitial ? '🔽' : '▶';
            btn.title = isExpandedInitial ? 'Ocultar membros' : 'Mostrar membros';

            btn.addEventListener('click', function() {
                const container = document.getElementById(membersId);
                if (!container) return;

                const expanded = this.getAttribute('aria-expanded') === 'true';

                if (expanded) {
                    container.classList.add('collapsed');
                    this.setAttribute('aria-expanded', 'false');
                    this.textContent = '▶';
                    this.title = 'Mostrar membros';
                } else {
                    container.classList.remove('collapsed');
                    this.setAttribute('aria-expanded', 'true');
                    this.textContent = '🔽';
                    this.title = 'Ocultar membros';
                }
            });
        });
    } catch (err) {
        // Não quebrar a página se ocorrer algo inesperado
        console.error('teams_hierarchy.js error:', err);
    }
});