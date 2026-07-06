@if ($activeTab === 'students' && $errors->has('student_number'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('addStudentModal');

            if (modalElement) {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }
        });
    </script>
@endif

@if ($activeTab === 'students' && $errors->has('student_file'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('addStudentModal');

            if (modalElement) {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }
        });
    </script>
@endif

@if ($activeTab === 'students' && $importPreview)
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalElement = document.getElementById('importPreviewModal');

            if (modalElement) {
                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            }
        });
    </script>
@endif

<script>
    document.addEventListener('click', function (event) {
        const copyButton = event.target.closest('[data-copy-target]');

        if (! copyButton || ! navigator.clipboard) {
            return;
        }

        const input = document.getElementById(copyButton.dataset.copyTarget);

        if (! input) {
            return;
        }

        navigator.clipboard.writeText(input.value).then(() => {
            copyButton.querySelector('.copy-label').textContent = 'Copied';
        });
    });
</script>
