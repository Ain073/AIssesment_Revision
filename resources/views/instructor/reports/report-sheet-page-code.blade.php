<script>
    (() => {
        const paperOptions = @json(collect($paperOptions)->map(fn ($option) => $option['description']));
        const paperSize = document.getElementById('paperSize');
        const paperSizeInput = document.getElementById('paperSizeInput');
        const paperSizeCaption = document.getElementById('paperSizeCaption');

        paperSize?.addEventListener('change', () => {
            const url = new URL(window.location.href);
            url.searchParams.set('paper', paperSize.value);
            paperSizeInput.value = paperSize.value;
            paperSizeCaption.textContent = paperOptions[paperSize.value] ?? '';
            window.location.href = url.toString();
        });

        const syncPrintText = (textarea) => {
            const printText = textarea.nextElementSibling;

            if (printText?.classList.contains('report-print-text')) {
                printText.textContent = textarea.value;
            }
        };

        const autosize = (textarea) => {
            textarea.style.height = 'auto';
            textarea.style.height = `${Math.max(textarea.scrollHeight, 136)}px`;
            syncPrintText(textarea);
        };

        document.querySelectorAll('.report-edit-textarea').forEach((textarea) => {
            autosize(textarea);
            textarea.addEventListener('input', () => autosize(textarea));
            textarea.addEventListener('change', () => autosize(textarea));
        });

        window.addEventListener('beforeprint', () => {
            document.querySelectorAll('.report-edit-textarea').forEach(autosize);
        });

        window.addEventListener('load', () => {
            document.querySelectorAll('.report-edit-textarea').forEach(autosize);
        });

        const aiButton = document.getElementById('generateAiDraftsButton');
        const aiProvider = document.getElementById('aiProvider');
        const aiStatus = document.getElementById('reportAiStatus');
        const reportForm = document.getElementById('reportSheetForm');

        const showAiStatus = (message, type = 'info') => {
            if (! aiStatus) {
                return;
            }

            aiStatus.className = `alert alert-${type} report-ai-status show`;
            aiStatus.textContent = message;
        };

        const fillAiDrafts = (drafts) => {
            Object.entries(drafts).forEach(([assessmentKey, draft]) => {
                Object.entries(draft).forEach(([field, value]) => {
                    const textarea = document.querySelector(`[data-assessment-key="${assessmentKey}"][data-ai-field="${field}"]`);

                    if (textarea && value && field !== 'source') {
                        textarea.value = value;
                        autosize(textarea);
                    }
                });
            });
        };

        aiButton?.addEventListener('click', async () => {
            const classAssessmentKeys = [...reportForm.querySelectorAll('input[name="class_assessment_keys[]"]')]
                .map((input) => input.value.trim())
                .filter(Boolean);

            if (classAssessmentKeys.length === 0) {
                showAiStatus('No completed assessments were selected.', 'warning');
                return;
            }

            aiButton.disabled = true;
            const selectedAiLabel = aiProvider?.selectedOptions?.[0]?.textContent?.trim() || 'selected AI candidate';
            showAiStatus(`Generating AI draft content with ${selectedAiLabel}...`, 'info');

            try {
                const response = await fetch(aiButton.dataset.aiUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': window.csrfToken,
                    },
                    body: JSON.stringify({
                        report_type: @json($reportType),
                        class_assessment_keys: classAssessmentKeys,
                        ai_provider: aiProvider?.value || 'openai',
                    }),
                });

                const data = await response.json();

                if (! response.ok) {
                    throw new Error(data.message ?? 'AI draft request failed.');
                }

                fillAiDrafts(data.drafts ?? {});

                showAiStatus(`AI draft generated with ${selectedAiLabel}. Review and edit before saving.`, 'success');
            } catch (error) {
                showAiStatus(error.message || 'Unable to generate AI draft. Please try again.', 'danger');
            } finally {
                aiButton.disabled = false;
            }
        });
    })();
</script>
