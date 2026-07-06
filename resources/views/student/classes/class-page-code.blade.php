@if ($errors->has('join_code'))
    <script>
        const joinClassModal = document.getElementById('joinClassModal');

        if (joinClassModal) {
            bootstrap.Modal.getOrCreateInstance(joinClassModal).show();
        }
    </script>
@endif
