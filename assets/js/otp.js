document.addEventListener("DOMContentLoaded", function() {
    let pendingUser = null;
    try {
        pendingUser = JSON.parse(sessionStorage.getItem("pending_user"));
    } catch (e) {
        pendingUser = null;
    }

    let email = pendingUser ? pendingUser.email : "testuser@secureauth.com";
    let emailDisplay = document.getElementById("user-email-display");
    if (emailDisplay) {
        emailDisplay.textContent = email;
    }

    let otpInputs = document.querySelectorAll(".otp-boxes");
    let form = document.getElementById("otp-form");
    let message = document.getElementById("displaymessage");

    // Auto-focus first box
    if (otpInputs.length > 0) {
        otpInputs[0].focus();
    }

    // Auto-advance & backspace
    otpInputs.forEach((input, index) => {
        input.addEventListener("input", function(e) {
            // Remove non-numeric
            this.value = this.value.replace(/[^0-9]/g, "");

            if (this.value.length === 1 && index < otpInputs.length - 1) {
                otpInputs[index + 1].focus();
            }
        });

        input.addEventListener("keydown", function(e) {
            if (e.key === "Backspace" && !this.value && index > 0) {
                otpInputs[index - 1].focus();
            }
        });

        // Paste support
        input.addEventListener("paste", function(e) {
            e.preventDefault();
            let pasteData = (e.clipboardData || window.clipboardData).getData("text").trim();
            if (/^\d+$/.test(pasteData)) {
                let digits = pasteData.split("");
                otpInputs.forEach((box, i) => {
                    if (digits[i]) {
                        box.value = digits[i];
                    }
                });
                let nextFocus = Math.min(digits.length, otpInputs.length - 1);
                otpInputs[nextFocus].focus();
            }
        });
    });

    // Countdown Timer & Resend
    let resendContainer = document.querySelector(".resend-text");
    let timer = null;

    function startCountdown() {
        let secondsLeft = 60;
        if (resendContainer) {
            resendContainer.innerHTML = 'Resend email code in <span id="countdown">' + secondsLeft + 's</span>';
        }
        clearInterval(timer);
        timer = setInterval(function() {
            secondsLeft--;
            let countdownEl = document.getElementById("countdown");
            if (countdownEl) {
                countdownEl.textContent = secondsLeft + "s";
            }
            if (secondsLeft <= 0) {
                clearInterval(timer);
                if (resendContainer) {
                    resendContainer.innerHTML = '<a href="#" id="resend-link" style="color: #60a5fa; text-decoration: underline; cursor: pointer;">Resend verification code</a>';
                    let resendLink = document.getElementById("resend-link");
                    if (resendLink) {
                        resendLink.addEventListener("click", async function(ev) {
                            ev.preventDefault();
                            message.style.color = "var(--text-secondary)";
                            message.textContent = "Sending new verification code...";
                            try {
                                let res = await fetch("../php/resend_otp.php", {
                                    method: "POST",
                                    headers: { "Content-Type": "application/json" },
                                    body: JSON.stringify({ email: email })
                                });
                                let resData = await res.json();
                                if (resData.success) {
                                    message.style.color = "#4ade80";
                                    message.textContent = resData.message;
                                    startCountdown();
                                } else {
                                    message.style.color = "#f87171";
                                    message.textContent = resData.message;
                                }
                            } catch (e) {
                                message.style.color = "#f87171";
                                message.textContent = "Unable to resend verification code.";
                            }
                        });
                    }
                }
            }
        }, 1000);
    }

    startCountdown();

    // Form Submit
    form.addEventListener("submit", async function(e) {
        e.preventDefault();

        let enteredCode = "";
        otpInputs.forEach(input => {
            enteredCode += input.value.trim();
        });

        if (enteredCode.length !== 6) {
            message.style.color = "#f87171";
            message.textContent = "Please enter all 6 digits of the code.";
            return;
        }

        message.style.color = "var(--text-secondary)";
        message.textContent = "Verifying security code...";

        try {
            let response = await fetch("../php/verify_otp.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    email: email,
                    otp: enteredCode
                })
            });

            let data = await response.json();
            console.log("OTP Verification response:", data);

            if (data.success) {
                message.style.color = "#4ade80";
                message.textContent = data.message;

                // Save logged in state
                sessionStorage.setItem("client_user", JSON.stringify(pendingUser || { email: email, full_name: "Test User" }));
                sessionStorage.removeItem("pending_user");

                setTimeout(function() {
                    window.location.href = "demo_client_app.html?logged_in=1";
                }, 1000);
            } else {
                message.style.color = "#f87171";
                message.textContent = data.message;
            }
        } catch (err) {
            console.log("Error:", err);
            message.style.color = "#f87171";
            message.textContent = "Unable to connect to server.";
        }
    });
});
