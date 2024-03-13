var pageType = document.getElementById('type');

pageType.onchange = function () {
    showPages(pageType.value);
};

function showPages(pageId) {
    var pageTypes = ['category', 'product', 'content', 'folder', 'brand', 'url'];
    var hiddenPage = pageTypes.filter(function (value, index, array) {
        return value !== pageId;
    });
    var currType = document.getElementById(pageId+'_page');
    if (currType.classList.contains('hidden')) {
        currType.classList.remove('hidden');
    }
    hiddenPage.forEach(function (pageName) {
        var pageElement = document.getElementById(pageName+'_page');
        if (!pageElement.classList.contains('hidden')){
            pageElement.classList.add('hidden');
        }
    })
}