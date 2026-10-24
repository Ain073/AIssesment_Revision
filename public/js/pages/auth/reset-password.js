document.getElementById("resetPasswordForm").addEventListener("submit", function (event) {
    const button = event.target.querySelector('button[type="submit"]');
    button.innerHTML = '<span class="material-symbols-outlined spinner-icon">progress_activity</span> Resetting...';
    button.disabled = true;
});

document.querySelectorAll("[data-toggle-password]").forEach((button) => {
    button.addEventListener("click", function () {
        const input = document.getElementById(this.dataset.togglePassword);
        const icon = this.querySelector(".material-symbols-outlined");
        const isPassword = input.type === "password";

        input.type = isPassword ? "text" : "password";
        icon.textContent = isPassword ? "visibility_off" : "visibility";
    });
});
