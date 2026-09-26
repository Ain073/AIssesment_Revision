<script>
    function filterDeanList() {
        const collegeId = document.getElementById('deanCollegeFilter')?.value || '';
        const q = (document.getElementById('searchDeanFacultyInput')?.value || '').toLowerCase().trim();
        const items = document.querySelectorAll('.dean-picker-item');
        let count = 0;

        items.forEach(item => {
            const itemColId = item.getAttribute('data-college-id') || '';
            const searchData = item.getAttribute('data-search') || '';

            const matchesCollege = !collegeId || itemColId === collegeId;
            const matchesQuery = !q || searchData.includes(q);

            if (matchesCollege && matchesQuery) {
                item.classList.remove('d-none');
                count++;
            } else {
                item.classList.add('d-none');
            }
        });

        const emptyMsg = document.getElementById('noDeanFacultyFound');
        if (emptyMsg) {
            emptyMsg.classList.toggle('d-none', count > 0 || items.length === 0);
        }
    }

    function openDeanConfirmDialog(userId, userName, collegeName, deptName, userEmail) {
        document.getElementById('deanConfirmUserId').value = userId;
        document.getElementById('deanConfirmName').textContent = userName;
        document.getElementById('deanConfirmCollege').textContent = collegeName + (deptName ? ' • ' + deptName : '');
        document.getElementById('deanConfirmAvatar').textContent = (userName.charAt(0) || 'U').toUpperCase();
        const emailEl = document.getElementById('deanConfirmEmail');
        if (emailEl) {
            emailEl.textContent = userEmail || '';
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('deanConfirmModal'));
        modal.show();
    }
</script>

@if ($errors->any())
    <script>
        const formMode = @json(old('form_mode'));
        const modalId = formMode === 'grant_admin_dean'
                ? 'grantAdminDeanModal'
                : null;

        if (modalId) {
            const modalElement = document.getElementById(modalId);

            if (modalElement) {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }
        }
    </script>
@endif

