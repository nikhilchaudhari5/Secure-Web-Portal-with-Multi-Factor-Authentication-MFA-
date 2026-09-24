let loginForm = document.querySelector(".login-card");
let message = document.querySelector("#displaymessage");

loginForm.addEventListener("submit", async function(event) {
    event.preventDefault();

    let email = document.querySelector("#email").value.trim();
    let password = document.querySelector("#password").value;

    message.style.color = "var(--text-secondary)";
    message.textContent = "Signing in...";

    try {
        let response = await fetch("../php/login.php", {
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
        console.log("PHP Response:", data);

        if (data.success) {
            message.style.color = "#4ade80"; // Green for success
            message.textContent = "Password verified. Redirecting to OTP verification...";
            sessionStorage.setItem("pending_user", JSON.stringify(data.user));
            setTimeout(function() {
                window.location.href = "otp.html";
            }, 800);
        } else {
            message.style.color = "#f87171"; // Red for failure
            message.textContent = data.message;
        }

    } catch (error) {
        console.log("Error:", error);
        message.style.color = "#f87171";
        message.textContent = "Unable to connect to server.";
    }
});