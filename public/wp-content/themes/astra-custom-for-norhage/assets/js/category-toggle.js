document.addEventListener('DOMContentLoaded', function () {
    const currentURL = window.location.href;
    const categoryItems = document.querySelectorAll('.wc-block-product-categories-list-item');

    categoryItems.forEach(function (item) {
        const subList = item.querySelector('.wc-block-product-categories-list');
        const link = item.querySelector('a');

        // HIDE "Uncategorised"
        if (link && link.textContent.trim().toLowerCase() === 'uncategorised') {
            item.style.display = 'none';
            return;
        }

        // MARK CURRENT CATEGORY
        if (link && currentURL.includes(link.getAttribute('href'))) {
            item.classList.add('current-category');
        }

        // HANDLE PARENT CATEGORIES WITH CHILDREN
        if (subList) {
            subList.style.display = 'none';

            // Create toggle icon
            const toggle = document.createElement('span');
            toggle.classList.add('category-toggle-icon');
            toggle.innerHTML = `
                <svg class="toggle-triangle" width="10" height="10" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                    <polygon points="5,4 15,10 5,16" fill="#2EC9AD"/>
                </svg>
            `;

            // Insert toggle before category name
            const nameSpan = item.querySelector('.wc-block-product-categories-list-item__name');
            if (nameSpan) {
                nameSpan.parentElement.insertBefore(toggle, nameSpan);
            }

            // Add "All {Category}" link
            const parentLink = item.querySelector('a')?.getAttribute('href');
            const parentName = nameSpan?.textContent?.trim();
            if (parentLink && parentName) {
                const allItem = document.createElement('li');
                allItem.classList.add('wc-block-product-categories-list-item');
                allItem.innerHTML = `
                    <a href="${parentLink}">
                        <span class="wc-block-product-categories-list-item__name">All ${parentName}</span>
                    </a>
                `;
                subList.prepend(allItem);
            }

            // Toggle behavior
            const toggleSubmenu = () => {
                const isOpen = subList.style.display === 'block';
                document.querySelectorAll('.wc-block-product-categories-list .wc-block-product-categories-list')
                    .forEach(otherList => {
                        if (otherList !== subList) {
                            otherList.style.display = 'none';
                            const icon = otherList.parentElement.querySelector('.toggle-triangle');
                            if (icon) icon.classList.remove('rotated');
                        }
                    });
                subList.style.display = isOpen ? 'none' : 'block';
                toggle.querySelector('svg').classList.toggle('rotated', !isOpen);
            };

            toggle.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSubmenu();
            });

            nameSpan?.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                toggleSubmenu();
            });

            // AUTO-EXPAND IF CHILD IS CURRENT
            const childLinks = subList.querySelectorAll('a');
            childLinks.forEach(child => {
                if (currentURL.includes(child.getAttribute('href'))) {
                    subList.style.display = 'block';
                    toggle.querySelector('svg').classList.add('rotated');
                }
            });
        }
    });
});
