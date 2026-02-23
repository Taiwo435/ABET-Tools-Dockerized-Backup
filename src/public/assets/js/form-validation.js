document.addEventListener("DOMContentLoaded", function () {

    const form = document.querySelector("form");
    if (!form || !formConfig) return;
    
    form.addEventListener("submit", function (e) {

        let errors = [];

        formConfig.fields.forEach(field => {
            return;
            const input = form.querySelector(`[name="${field.name}"]`);
            if (!input) return;

            const value = input.value.trim();

            // Required validation
            if (field.required && value === "") {
                errors.push(`${field.label} is required.`);
                showError(input, `${field.label} is required.`);
                return;
            }

            // Min length validation
            if (field.minLength && value.length < field.minLength) {
                errors.push(`${field.label} must be at least ${field.minLength} characters.`);
                showError(input, `${field.label} must be at least ${field.minLength} characters.`);
            }

            // Email validation
            if (field.type === "email" && value !== "") {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    errors.push("Invalid email format.");
                    showError(input, "Invalid email format.");
                }
            }

        });
        
        if (errors.length > 0) {
            e.preventDefault();
        }

    });

    function showError(input, message) {
        clearError(input);

        const error = document.createElement("div");
        error.className = "error";
        error.innerText = message;

        input.parentNode.appendChild(error);
        input.classList.add("input-error");
    }

    function clearError(input) {
        const existing = input.parentNode.querySelector(".error");
        if (existing) existing.remove();
        input.classList.remove("input-error");
    }

});