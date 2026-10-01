<script>
    let sortDirections = {};

    function sortTable(n) {
        const table = document.getElementById("player_filter");
        const tbody = table.querySelector("tbody");
        const rows = Array.from(tbody.querySelectorAll("tr"));

        // Toggle direction
        sortDirections[n] = sortDirections[n] === "asc" ? "desc" : "asc";
        const dir = sortDirections[n];

        rows.sort(function(a, b) {
            const x = a.querySelectorAll("td")[n]?.innerText.trim().toLowerCase() ?? "";
            const y = b.querySelectorAll("td")[n]?.innerText.trim().toLowerCase() ?? "";

            // Try numeric comparison first
            const nx = parseFloat(x.replace(/[^0-9.-]/g, ""));
            const ny = parseFloat(y.replace(/[^0-9.-]/g, ""));

            if (!isNaN(nx) && !isNaN(ny)) {
                return dir === "asc" ? nx - ny : ny - nx;
            }

            return dir === "asc"
                ? x.localeCompare(y)
                : y.localeCompare(x);
        });

        // Reattach sorted rows in one operation
        rows.forEach(row => tbody.appendChild(row));
    }
</script>