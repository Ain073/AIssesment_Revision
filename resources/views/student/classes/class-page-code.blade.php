@if ($errors->has('join_code'))
    <script>
        const joinClassModal = document.getElementById('joinClassModal');

        if (joinClassModal) {
            bootstrap.Modal.getOrCreateInstance(joinClassModal).show();
        }
    </script>
@elseif (request('action') === 'join-class')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const joinClassModal = document.getElementById('joinClassModal');
            if (joinClassModal) {
                bootstrap.Modal.getOrCreateInstance(joinClassModal).show();
            }
        });
    </script>
@endif
