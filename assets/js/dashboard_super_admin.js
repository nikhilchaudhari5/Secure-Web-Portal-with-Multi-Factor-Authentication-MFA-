document.addEventListener("DOMContentLoaded", async function() {
    try {
        let response = await fetch("../../php/superadmin_dashboard_data.php");
        let data = await response.json();

        if (data.success) {
            let metrics = data.metrics;

            // 1. Top 5 Overview Cards (Simple Text)
            document.getElementById("card-mrr").textContent = metrics.mrr_formatted || "$0/mo";
            document.getElementById("card-mrr-breakdown").textContent = metrics.mrr_breakdown || "";
            document.getElementById("card-companies").textContent = metrics.total_companies;
            document.getElementById("card-users").textContent = metrics.total_users;
            document.getElementById("card-sessions").textContent = metrics.total_active_sessions;
            document.getElementById("card-lockouts").textContent = metrics.total_lockouts;

            // 2. Populate Subscribed Tenant Companies Directory as Simple Text
            let tenantsContainer = document.getElementById("tenants-rows");
            tenantsContainer.innerHTML = "";

            if (data.companies && data.companies.length > 0) {
                data.companies.forEach(function(comp) {
                    let onlineText = comp.active_sessions_count > 0 
                        ? `<span class="text-success">${comp.active_sessions_count} Online</span>`
                        : `<span class="text-muted">0 Online</span>`;

                    let lockText = comp.locked_users_count > 0
                        ? `<span class="text-danger">${comp.locked_users_count} Locked</span>`
                        : `<span class="text-success">0 Locks</span>`;

                    let row = document.createElement("div");
                    row.className = "tenant-row";
                    row.innerHTML = `
                        <span style="font-weight: 600;">${escapeHtml(comp.company_name)}</span>
                        <span>${escapeHtml(comp.admin_email)}</span>
                        <span>${escapeHtml(comp.plan_tier)}</span>
                        <span>${comp.total_users} Users</span>
                        <span>${onlineText}</span>
                        <span>${lockText}</span>
                        <span class="text-success">${escapeHtml(comp.monthly_revenue)}</span>
                        <span style="color: var(--text-secondary);">${escapeHtml(comp.client_id)}</span>
                    `;
                    tenantsContainer.appendChild(row);
                });
            } else {
                tenantsContainer.innerHTML = `<div class="tenant-row"><span style="color: var(--text-muted);">No companies onboarded yet.</span></div>`;
            }

            // 3. Populate Global Security Activity as Simple Text
            let activityContainer = document.getElementById("global-activity-rows");
            activityContainer.innerHTML = "";

            if (data.audit_logs && data.audit_logs.length > 0) {
                data.audit_logs.forEach(function(log) {
                    let isSuccess = log.status === "SUCCESS" || log.status === "Success";
                    let isChallenge = log.status === "CHALLENGE" || log.status === "Challenge";
                    let statusClass = isSuccess ? "status-success" : (isChallenge ? "status-warning" : "status-failed");

                    let row = document.createElement("div");
                    row.className = "activity-row";
                    row.style.gridTemplateColumns = "1.4fr 1.6fr 1.3fr 1.1fr 1fr 1.1fr 1.4fr";
                    row.innerHTML = `
                        <span>${escapeHtml(log.action)}</span>
                        <span>${escapeHtml(log.user_email)}</span>
                        <span>${escapeHtml(log.company_name)}</span>
                        <span>${escapeHtml(log.auth_method)}</span>
                        <span class="${statusClass}">${escapeHtml(log.status)}</span>
                        <span>${escapeHtml(log.ip_address)}</span>
                        <span>${escapeHtml(log.timestamp)}</span>
                    `;
                    activityContainer.appendChild(row);
                });
            } else {
                activityContainer.innerHTML = `<div class="activity-row"><span style="color: var(--text-muted);">No security logs recorded.</span></div>`;
            }
        }
    } catch (err) {
        console.log("Failed to load superadmin dashboard data:", err);
    }

    // Logout
    let logoutBtn = document.getElementById("logout-btn");
    if (logoutBtn) {
        logoutBtn.addEventListener("click", function() {
            sessionStorage.removeItem("superadmin");
            window.location.href = "login_superadmin.html";
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
