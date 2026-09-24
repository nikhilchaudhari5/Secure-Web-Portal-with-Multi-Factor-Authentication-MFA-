let form = document.querySelector(".registration-page");
let message = document.querySelector("#displaymessage");

form.addEventListener("submit", async function(event) {
    event.preventDefault();

    let name = document.querySelector("#name").value.trim();
    let email = document.querySelector("#email").value.trim();
    let password = document.querySelector("#password").value;
    let confirmPassword = document.querySelector("#confirm_password").value;

    if (password !== confirmPassword) {
        message.style.color = "#f87171";
        message.textContent = "Passwords do not match.";
        return;
    }

    if (password.length < 8) {
        message.style.color = "#f87171";
        message.textContent = "Password must be at least 8 characters long.";
        return;
    }

    message.style.color = "var(--text-secondary)";
    message.textContent = "Creating your account...";

    try {
        let response = await fetch("../php/user_register.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                name: name,
                email: email,
                password: password
            })
        });

        let data = await response.json();
        console.log("Registration response:", data);

        if (data.success) {
            message.style.color = "#4ade80";
            message.textContent = data.message;
            setTimeout(function() {
                window.location.href = "user_login.html";
            }, 1200);
        } else {
            message.style.color = "#f87171";
            message.textContent = data.message;
        }
    } catch (error) {
        console.log("Error:", error);
        message.style.color = "#f87171";
        message.textContent = "Unable to connect to server.";
    }
});
