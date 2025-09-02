document.addEventListener('DOMContentLoaded', function () {
    // Para cada botão de expandir
    document.querySelectorAll('.expand-btn').forEach(function(btn) {
        btn.addEventListener('click', function () {
            const targetId = btn.getAttribute('data-target');
            const modal = document.getElementById(targetId);
            if (modal) modal.style.display = "block";

            // Fecha ao clicar no X
            const closeBtn = modal.querySelector('.close');
            if (closeBtn) {
                closeBtn.onclick = function () {
                    modal.style.display = "none";
                }
            }

            // Fecha ao clicar fora do modal-content
            window.onclick = function (event) {
                if (event.target === modal) {
                    modal.style.display = "none";
                }
            }
        });
    });
});