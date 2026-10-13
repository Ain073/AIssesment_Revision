(() => {
    const syncProfileFields = (roleSelect) => {
        const userId = roleSelect.id.replace('edit_base_role_', '');
        const isTeacher = roleSelect.value === 'instructor';
        const isStudent = roleSelect.value === 'student';

        [
            ['teacher_department', isTeacher],
            ['teacher_employee', isTeacher],
            ['student_program', isStudent],
            ['student_number', isStudent],
            ['authorization', isTeacher],
        ].forEach(([name, visible]) => {
            const section = document.getElementById(`edit_${name}_section_${userId}`);
            if (section) {
                section.hidden = !visible;
            }
        });

        [
            ['department_id', isTeacher],
            ['employee_number', isTeacher],
            ['program_id', isStudent],
            ['student_number', isStudent],
        ].forEach(([name, enabled]) => {
            const field = document.getElementById(`edit_${name}_${userId}`);
            if (field) {
                field.disabled = !enabled;
                field.required = enabled;
                if (!enabled) {
                    field.value = '';
                }
            }
        });

        document.querySelectorAll(`.authorization-checkbox[data-teacher-target="${roleSelect.id}"]`)
            .forEach((checkbox) => {
                checkbox.disabled = !isTeacher;
                if (!isTeacher) {
                    checkbox.checked = false;
                }
            });
    };

    const syncUserFilters = (tabName) => {
        const activeTabInput = document.getElementById('activeTabInput');
        if (activeTabInput) {
            activeTabInput.value = tabName;
        }
        document.querySelectorAll('[data-user-filter-tab]').forEach((filter) => {
            filter.hidden = filter.dataset.userFilterTab !== tabName;
        });
    };

    // Delegation keeps newly fetched edit forms working without rebinding listeners.
    document.addEventListener('change', (event) => {
        const roleSelect = event.target.closest('select[id^="edit_base_role_"]');
        if (roleSelect) {
            syncProfileFields(roleSelect);
        }

        const checkbox = event.target.closest('.authorization-checkbox');
        if (checkbox?.checked) {
            document.querySelectorAll(`.authorization-checkbox[data-teacher-target="${checkbox.dataset.teacherTarget}"]`)
                .forEach((related) => {
                    if (related !== checkbox) {
                        related.checked = false;
                    }
                });
        }
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('#userTabs [data-table-tab-button]');
        if (button) {
            syncUserFilters(button.dataset.tableTabButton);
        }
    });

    window.initializeUserDirectory = (root = document) => {
        root.querySelectorAll('select[id^="edit_base_role_"]').forEach(syncProfileFields);
        const tabs = document.getElementById('userTabs')?.closest('[data-table-tabs-root]');
        syncUserFilters(tabs?.dataset.tableTabsSelected || document.getElementById('activeTabInput')?.value || 'teachers');
    };
})();
