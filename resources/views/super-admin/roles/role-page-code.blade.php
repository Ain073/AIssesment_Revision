@if ($errors->any())
        <script>
            const formMode = @json(old('form_mode'));
            const modalId = formMode === 'grant_department_chair'
                ? 'grantDepartmentChairModal'
                : formMode === 'grant_admin_dean'
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