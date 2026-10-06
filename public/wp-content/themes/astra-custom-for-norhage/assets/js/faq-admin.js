(function () {
    var form = document.getElementById('nh-faq-admin-form');

    if (!form) {
        return;
    }

    var topicList = document.getElementById('nh-faq-topics');
    var itemList = document.getElementById('nh-faq-items');
    var topicTemplate = document.getElementById('nh-faq-topic-template');
    var itemTemplate = document.getElementById('nh-faq-item-template');

    function slugify(value) {
        return String(value || '')
            .toLowerCase()
            .replace(/['"]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function reindex() {
        if (topicList) {
            topicList.querySelectorAll('.nh-faq-topic-row').forEach(function (row, index) {
                row.querySelectorAll('[name]').forEach(function (field) {
                    field.name = field.name.replace(/nh_faq_topics\[[^\]]+\]/, 'nh_faq_topics[' + index + ']');
                });
            });
        }

        if (itemList) {
            itemList.querySelectorAll('.nh-faq-admin-item').forEach(function (row, index) {
                row.querySelectorAll('[name]').forEach(function (field) {
                    field.name = field.name.replace(/nh_faq_items\[[^\]]+\]/, 'nh_faq_items[' + index + ']');
                });

                var choices = row.querySelector('.nh-faq-item-topics');

                if (choices) {
                    choices.setAttribute('data-name', 'nh_faq_items[' + index + '][topics][]');
                }
            });
        }
    }

    function readTopics() {
        var topics = [];

        if (!topicList) {
            return topics;
        }

        topicList.querySelectorAll('.nh-faq-topic-row').forEach(function (row) {
            var labelField = row.querySelector('.nh-faq-topic-label');
            var idField = row.querySelector('.nh-faq-topic-id');
            var label = labelField ? labelField.value.trim() : '';

            if (!label) {
                return;
            }

            var id = idField ? idField.value.trim() : '';

            if (!id) {
                id = slugify(label);
                if (idField) {
                    idField.value = id;
                }
            }

            if (!id) {
                return;
            }

            topics.push({ id: id, label: label });
        });

        return topics;
    }

    function refreshTopicChoices() {
        var topics = readTopics();

        document.querySelectorAll('.nh-faq-item-topics').forEach(function (box) {
            var selected = {};

            box.querySelectorAll('input[type="checkbox"]').forEach(function (input) {
                if (input.checked) {
                    selected[input.value] = true;
                }
            });

            box.innerHTML = '';
            topics.forEach(function (topic) {
                var label = document.createElement('label');
                var input = document.createElement('input');
                input.type = 'checkbox';
                input.name = box.getAttribute('data-name') || '';
                input.value = topic.id;
                input.checked = !!selected[topic.id];
                label.appendChild(input);
                label.appendChild(document.createTextNode(' ' + topic.label));
                box.appendChild(label);
            });
        });
    }

    function moveRow(row, direction) {
        var sibling = direction < 0 ? row.previousElementSibling : row.nextElementSibling;

        while (sibling && sibling.hidden) {
            sibling = direction < 0 ? sibling.previousElementSibling : sibling.nextElementSibling;
        }

        if (!sibling || sibling.parentNode !== row.parentNode) {
            return;
        }

        var orderA = row.querySelector('input[type="number"]');
        var orderB = sibling.querySelector('input[type="number"]');

        if (orderA && orderB) {
            var swap = orderA.value;
            orderA.value = orderB.value;
            orderB.value = swap;
        }

        if (direction < 0) {
            row.parentNode.insertBefore(row, sibling);
        } else {
            row.parentNode.insertBefore(sibling, row);
        }
    }

    function nextTopicOrder() {
        var max = 0;

        if (!topicList) {
            return 10;
        }

        topicList.querySelectorAll('input[type="number"]').forEach(function (field) {
            var value = parseInt(field.value, 10);

            if (!isNaN(value) && value > max) {
                max = value;
            }
        });

        return max + 10;
    }

    form.addEventListener('click', function (event) {
        var button = event.target.closest('button');

        if (!button) {
            return;
        }

        if (button.classList.contains('nh-faq-add-topic') && topicTemplate && topicList) {
            topicList.appendChild(topicTemplate.content.cloneNode(true));
            var topicRows = topicList.querySelectorAll('.nh-faq-topic-row');
            var addedTopic = topicRows[topicRows.length - 1];
            var orderField = addedTopic ? addedTopic.querySelector('input[type="number"]') : null;

            if (orderField && orderField.value === '') {
                orderField.value = String(nextTopicOrder());
            }

            reindex();
            refreshTopicChoices();

            var topicLabel = addedTopic ? addedTopic.querySelector('.nh-faq-topic-label') : null;

            if (topicLabel) {
                topicLabel.focus();
            }
        }

        if (button.classList.contains('nh-faq-add-item') && itemTemplate && itemList) {
            itemList.appendChild(itemTemplate.content.cloneNode(true));
            reindex();
            refreshTopicChoices();

            var addedItem = itemList.lastElementChild;
            var questionField = addedItem ? addedItem.querySelector('.nh-faq-question') : null;

            if (questionField) {
                questionField.focus();
            }
        }

        if (button.classList.contains('nh-faq-move')) {
            var row = button.closest('.nh-faq-admin-item, .nh-faq-topic-row');

            if (row) {
                moveRow(row, button.classList.contains('is-up') ? -1 : 1);
            }
        }
    });

    form.addEventListener('input', function (event) {
        if (event.target.classList.contains('nh-faq-topic-label') || event.target.classList.contains('nh-faq-delete')) {
            var row = event.target.closest('.nh-faq-topic-row');

            if (row && !row.dataset.locked) {
                var idField = row.querySelector('.nh-faq-topic-id');
                var labelField = row.querySelector('.nh-faq-topic-label');

                if (idField && labelField && !idField.dataset.keep) {
                    var previousId = idField.value;
                    var nextId = slugify(labelField.value);

                    if (previousId && nextId && previousId !== nextId) {
                        document.querySelectorAll('.nh-faq-item-topics input').forEach(function (input) {
                            if (input.value === previousId) {
                                input.value = nextId;
                            }
                        });
                    }

                    idField.value = nextId;
                }
            }

            var deleted = event.target.closest('.nh-faq-admin-item, .nh-faq-topic-row');

            if (deleted && event.target.classList.contains('nh-faq-delete')) {
                deleted.classList.toggle('is-deleted', event.target.checked);
            }

            reindex();
            refreshTopicChoices();
        }

        if (event.target.id === 'nh-faq-admin-filter' && itemList) {
            var query = event.target.value.toLowerCase();

            itemList.querySelectorAll('.nh-faq-admin-item').forEach(function (row) {
                var question = row.querySelector('.nh-faq-question');
                var text = question ? question.value.toLowerCase() : '';
                row.hidden = query !== '' && text.indexOf(query) === -1;
            });
        }
    });

    form.addEventListener('submit', function () {
        reindex();
        refreshTopicChoices();
    });

    var restore = document.getElementById('nh-faq-restore');

    if (restore) {
        restore.addEventListener('submit', function (event) {
            var message = restore.getAttribute('data-confirm') || '';

            if (message && !window.confirm(message)) {
                event.preventDefault();
            }
        });
    }
})();
