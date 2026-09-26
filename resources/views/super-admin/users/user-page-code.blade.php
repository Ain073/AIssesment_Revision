<script>
    // The edit popup can switch an account between teacher and student.
    function syncAuthorizationInputs(selectId) {
        const roleSelect = document.getElementById(selectId);

        if (! roleSelect) {
            return;
        }

        const checkboxes = document.querySelectorAll(`.authorization-checkbox[data-teacher-target="${selectId}"]`);
        const isTeacher = roleSelect.value === 'instructor';

        checkboxes.forEach((checkbox) => {
            checkbox.disabled = ! isTeacher;

            if (! isTeacher) {
                checkbox.checked = false;
            }
        });
    }

    function syncExclusiveAuthorizations() {
        document.querySelectorAll('.authorization-checkbox').forEach((checkbox) => {
            checkbox.addEventListener('change', () => {
                if (! checkbox.checked) {
                    return;
                }

                const target = checkbox.dataset.teacherTarget;

                document
                    .querySelectorAll(`.authorization-checkbox[data-teacher-target="${target}"]`)
                    .forEach((relatedCheckbox) => {
                        if (relatedCheckbox !== checkbox) {
                            relatedCheckbox.checked = false;
                        }
                    });
            });
        });
    }

    function syncDepartmentInput(selectId, departmentSelectId) {
        const roleSelect = document.getElementById(selectId);
        const departmentSelect = document.getElementById(departmentSelectId);

        if (! roleSelect || ! departmentSelect) {
            return;
        }

        const isTeacher = roleSelect.value === 'instructor';
        departmentSelect.disabled = ! isTeacher;
        departmentSelect.required = isTeacher;

        if (! isTeacher) {
            departmentSelect.value = '';
        }
    }

    function syncProfileFields(selectId) {
        const roleSelect = document.getElementById(selectId);

        if (! roleSelect) {
            return;
        }

        const userId = selectId.replace('edit_base_role_', '');
        const employeeInput = document.getElementById(`edit_employee_number_${userId}`);
        const programSelect = document.getElementById(`edit_program_id_${userId}`);
        const studentNumberInput = document.getElementById(`edit_student_number_${userId}`);
        const teacherDepartmentSection = document.getElementById(`edit_teacher_department_section_${userId}`);
        const teacherEmployeeSection = document.getElementById(`edit_teacher_employee_section_${userId}`);
        const studentProgramSection = document.getElementById(`edit_student_program_section_${userId}`);
        const studentNumberSection = document.getElementById(`edit_student_number_section_${userId}`);
        const authorizationSection = document.getElementById(`edit_authorization_section_${userId}`);
        const isTeacher = roleSelect.value === 'instructor';
        const isStudent = roleSelect.value === 'student';

        if (teacherDepartmentSection) {
            teacherDepartmentSection.hidden = ! isTeacher;
        }

        if (teacherEmployeeSection) {
            teacherEmployeeSection.hidden = ! isTeacher;
        }

        if (studentProgramSection) {
            studentProgramSection.hidden = ! isStudent;
        }

        if (studentNumberSection) {
            studentNumberSection.hidden = ! isStudent;
        }

        if (authorizationSection) {
            authorizationSection.hidden = ! isTeacher;
        }

        if (employeeInput) {
            employeeInput.disabled = ! isTeacher;
            employeeInput.required = isTeacher;

            if (! isTeacher) {
                employeeInput.value = '';
            }
        }

        if (programSelect) {
            programSelect.disabled = ! isStudent;
            programSelect.required = isStudent;

            if (! isStudent) {
                programSelect.value = '';
            }
        }

        if (studentNumberInput) {
            studentNumberInput.disabled = ! isStudent;
            studentNumberInput.required = isStudent;

            if (! isStudent) {
                studentNumberInput.value = '';
            }
        }
    }

    document.querySelectorAll('select[id^="edit_base_role_"]').forEach((select) => {
        syncAuthorizationInputs(select.id);
        select.addEventListener('change', () => syncAuthorizationInputs(select.id));
        syncDepartmentInput(select.id, select.id.replace('edit_base_role_', 'edit_department_id_'));
        select.addEventListener('change', () => syncDepartmentInput(select.id, select.id.replace('edit_base_role_', 'edit_department_id_')));
        syncProfileFields(select.id);
        select.addEventListener('change', () => syncProfileFields(select.id));
    });

    syncExclusiveAuthorizations();

    // Live Department Filtering for Teachers and Students
    (() => {
        const deptSelect = document.getElementById('departmentFilterSelect');
        const clearBtn = document.getElementById('clearDeptFilterBtn');
        if (! deptSelect) return;

        function applyDepartmentFilter(deptId) {
            let teachersVisible = 0;
            let studentsVisible = 0;

            document.querySelectorAll('table.teachers-table tbody tr[data-user-row]').forEach((row) => {
                const rowDept = row.dataset.departmentId || '';
                const matches = ! deptId || rowDept === deptId;
                row.style.display = matches ? '' : 'none';
                if (matches) teachersVisible++;
            });

            document.querySelectorAll('table.students-table tbody tr[data-user-row]').forEach((row) => {
                const rowDept = row.dataset.departmentId || '';
                const matches = ! deptId || rowDept === deptId;
                row.style.display = matches ? '' : 'none';
                if (matches) studentsVisible++;
            });

            // Update tab count badges
            const teachersTabCount = document.getElementById('teachersTabCount');
            const studentsTabCount = document.getElementById('studentsTabCount');
            if (teachersTabCount) teachersTabCount.textContent = teachersVisible;
            if (studentsTabCount) studentsTabCount.textContent = studentsVisible;

            // Toggle clear button
            if (clearBtn) {
                clearBtn.classList.toggle('d-none', ! deptId);
            }

            // Toggle empty rows and update entries footer
            document.querySelectorAll('[data-table-tab-panel="teachers"]').forEach((panel) => {
                const emptyRow = panel.querySelector('[data-no-results-row]');
                if (emptyRow) {
                    emptyRow.classList.toggle('d-none', teachersVisible > 0);
                }
                const countText = panel.querySelector('[data-entries-count]');
                if (countText) {
                    countText.textContent = `Showing ${teachersVisible} ${teachersVisible === 1 ? 'entry' : 'entries'}`;
                }
            });

            document.querySelectorAll('[data-table-tab-panel="students"]').forEach((panel) => {
                const emptyRow = panel.querySelector('[data-no-results-row]');
                if (emptyRow) {
                    emptyRow.classList.toggle('d-none', studentsVisible > 0);
                }
                const countText = panel.querySelector('[data-entries-count]');
                if (countText) {
                    countText.textContent = `Showing ${studentsVisible} ${studentsVisible === 1 ? 'entry' : 'entries'}`;
                }
            });

            // Sync URL parameter without full page reload
            const url = new URL(window.location.href);
            if (deptId) {
                url.searchParams.set('department_id', deptId);
            } else {
                url.searchParams.delete('department_id');
            }
            window.history.replaceState({}, '', url.toString());
        }

        deptSelect.addEventListener('change', () => {
            applyDepartmentFilter(deptSelect.value);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', () => {
                deptSelect.value = '';
                applyDepartmentFilter('');
            });
        }

        // Apply immediately if a department is pre-selected on page load
        if (deptSelect.value) {
            applyDepartmentFilter(deptSelect.value);
        }
    })();
</script>

@if ($errors->any())
    <script>
        const formMode = "{{ old('form_mode', 'create') }}";
        const userId = "{{ old('user_id') }}";
        const baseRole = "{{ old('base_role', 'instructor') }}";
        const modalId = formMode === 'edit' && userId
            ? `editUserModal${userId}`
            : (baseRole === 'student' ? 'createStudentModal' : 'createInstructorModal');
        const modalElement = document.getElementById(modalId);

        if (modalElement) {
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        }
    </script>
@endif
