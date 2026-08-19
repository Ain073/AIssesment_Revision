<script>
    (() => {
        const collegeFilter = document.getElementById('programCollegeFilter');
        const shownCount = document.getElementById('programShownCount');
        const emptyState = document.getElementById('programFilterEmpty');
        const rows = [...document.querySelectorAll('.program-row[data-college-id]')];

        if (! collegeFilter || rows.length === 0) {
            return;
        }

        const updateProgramRows = () => {
            const selectedCollegeId = collegeFilter.value;
            const visibleRows = rows.filter((row) => {
                const isVisible = ! selectedCollegeId || row.dataset.collegeId === selectedCollegeId;
                row.classList.toggle('d-none', ! isVisible);

                return isVisible;
            });
            const highestStudentCount = Math.max(
                0,
                ...visibleRows.map((row) => Number.parseInt(row.dataset.students || '0', 10)),
            );

            visibleRows.forEach((row) => {
                const fill = row.querySelector('.program-fill');
                const studentCount = Number.parseInt(row.dataset.students || '0', 10);
                const width = highestStudentCount > 0
                    ? Math.max(Math.round((studentCount / highestStudentCount) * 100), studentCount > 0 ? 4 : 0)
                    : 0;

                fill?.style.setProperty('--bar-width', `${width}%`);
            });

            if (shownCount) {
                shownCount.textContent = `${visibleRows.length} shown`;
            }

            emptyState?.classList.toggle('d-none', visibleRows.length > 0);
        };

        collegeFilter.addEventListener('change', updateProgramRows);
        updateProgramRows();
    })();
</script>
