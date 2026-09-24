document.addEventListener("DOMContentLoaded", async function() {
    let storedCompany = null;
    try {
        storedCompany = JSON.parse(sessionStorage.getItem("company"));
    } catch (e) {
        storedCompany = null;
    }

    let companyId = storedCompany ? storedCompany.id : 1;

    try {
        let response = await fetch("../../php/admin_dashboard_data.php?company_id=" + encodeURIComponent(companyId));
        let data = await response.json();

        if (data.success) {
            let comp = data.company;
            let stats = data.stats;

            // Brand & Header
            let companyName = comp.name || "Organization";
            document.getElementById("company-brand-name").textContent = companyName;
            document.getElementById("company-header-name").textContent = companyName;

            let initials = companyName.split(" ").map(w => w[0]).join("").substring(0, 2).toUpperCase() || "AC";
            document.getElementById("company-avatar").textContent = initials;

            // Overview cards
            document.getElementById("sub-plan").textContent = comp.plan || "Enterprise";
            document.getElementById("users-count").textContent = stats.enrolled_users;
            document.getElementById("sessions-count").textContent = stats.active_sessions;
            document.getElementById("alerts-count").textContent = stats.security_alerts;

            // API credentials
            document.getElementById("display-client-id").value = comp.client_id || "";
            document.getElementById("display-api-key").value = comp.api_key || "";

            // Populate 6-Column Activity Table as Simple Text
            let rowsContainer = document.getElementById("activity-rows");
            rowsContainer.innerHTML = "";

            if (data.activities && data.activities.length > 0) {
                data.activities.forEach(function(act) {
                    let isSuccess = act.status === "Success" || act.status === "SUCCESS";
                    let isChallenge = act.status === "Challenge" || act.status === "CHALLENGE";
                    let statusClass = isSuccess ? "status-success" : (isChallenge ? "status-warning" : "status-failed");

                    let row = document.createElement("div");
                    row.className = "activity-row";
                    row.innerHTML = `
                        <span>${escapeHtml(act.action)}</span>
                        <span>${escapeHtml(act.user_email)}</span>
                        <span>${escapeHtml(act.auth_method)}</span>
                        <span class="${statusClass}">${escapeHtml(act.status)}</span>
                        <span>${escapeHtml(act.ip_address)}</span>
                        <span>${escapeHtml(act.timestamp)}</span>
                    `;
                    rowsContainer.appendChild(row);
                });
            } else {
                rowsContainer.innerHTML = `
                    <div class="activity-row">
                        <span style="color: var(--text-muted);">No activity recorded yet for this organization.</span>
                    </div>
                `;
            }
        }
    } catch (err) {
        console.log("Failed to load company dashboard data:", err);
    }

    // Sidebar navigation tabs
    let tabDashboard = document.getElementById("tab-dashboard");
    let tabIntegration = document.getElementById("tab-integration");
    let apiSection = document.getElementById("api-section");
    let overviewSection = document.getElementById("overview-section");
    let activitySection = document.getElementById("activity-section");

    if (tabDashboard && tabIntegration) {
        tabDashboard.addEventListener("click", function(e) {
            e.preventDefault();
            tabDashboard.classList.add("active");
            tabIntegration.classList.remove("active");
            overviewSection.style.display = "grid";
            activitySection.style.display = "block";
            apiSection.style.display = "none";
        });

        tabIntegration.addEventListener("click", function(e) {
            e.preventDefault();
            tabIntegration.classList.add("active");
            tabDashboard.classList.remove("active");
            overviewSection.style.display = "none";
            activitySection.style.display = "none";
            apiSection.style.display = "block";
        });
    }

    // Logout
    let logoutBtn = document.getElementById("logout-btn");
    if (logoutBtn) {
        logoutBtn.addEventListener("click", function() {
            sessionStorage.removeItem("company");
            window.location.href = "admin_login.html";
        });
    }
});

function escapeHtml(str) {
    if (!str) return "";
    return String(str)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}
