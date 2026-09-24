let loginForm = document.querySelector(".login-card");
let message = document.querySelector("#displaymessage");

loginForm.addEventListener("submit", async function(event) {
    event.preventDefault();

    let email = document.querySelector("#email").value.trim();
    let password = document.querySelector("#password").value;

    message.style.color = "var(--text-secondary)";
    message.textContent = "Signing in...";

    try {
        let response = await fetch("../../php/superadmin_login.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json"
            },
            body: JSON.stringify({
                email: email,
                password: password
            })
        });

        let data = await response.json();
        console.log("Super Admin Login Response:", data);

        if (data.success) {
            message.style.color = "#4ade80";
            message.textContent = data.message;
            sessionStorage.setItem("superadmin", JSON.stringify(data.user));
            setTimeout(function() {
                window.location.href = "dashboard_super_admin.html";
            }, 800);
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
