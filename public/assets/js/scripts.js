
document.addEventListener('DOMContentLoaded', (event) => {
    const currentYear = new Date().getFullYear();
    const creationYear = 2024;
    const footer = document.querySelector('.footer p');

    if (currentYear === creationYear) {
        footer.textContent = `© ${creationYear} StrideBR. Todos os direitos reservados.`;
    } else {
        footer.textContent = `© ${creationYear} - ${currentYear} StrideBR. Todos os direitos reservados.`;
    }
});