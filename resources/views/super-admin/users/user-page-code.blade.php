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
