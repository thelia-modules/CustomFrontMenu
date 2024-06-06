var MENU_NAMES
var MENU_LIST
var CURRENT_SELECTED_MENU_ID
var LOCALE
var loopsDictionary = {
    "brand": brandLoopData,
    "category": categoryLoopData,
    "content": contentLoopData,
    "folder": folderLoopData,
    "product": productLoopData
};
let CURRENT_ID = null
let allowUnload = false
let selectedLanguage
let quotePattern = '&280&quote&280&'
let percentPattern = '&280&percent&280&'

// Get from json
function getFromJson(json){
    return JSON.parse(decodeURIComponent(replaceQuoteAndPercent(json)))
}
// End get from json

// Get value by locale
function getValueByLocaleOf(element, locale) {
    if (!locale) {
        locale = LOCALE
    }
    let found = false
    let result
    for (const [key, val] of Object.entries(element)) {
        if (key === locale) {
            result = val
            found = true
            break
        }
        else if (key === 'en_US') {
            result = val
            found = true
        }
    }
    if (!found) {
        result = element[Object.keys(element)[0]]
    }
    return result
}
// End get value by locale

// Close closest modal
function closeClosestModal(element) {
    let modal = element.closest('.modal');
    if (modal) {
        let modalId = modal.getAttribute('id');
        $(`#${modalId}`).modal('hide');
    }
}
// End close closest modal

// Current id
function getCurrentId() {
    if (CURRENT_ID === null) {
        console.error("CURRENT_ID not set")
    }
    return CURRENT_ID
}

function setCurrentId(id) {
    CURRENT_ID = id
}
// End current id

// Get next id
function getNextId() {
    let nextId = 1
    let arrayOfIds = getAllIdOf(MENU_LIST)
    arrayOfIds.sort((a, b) => a - b)
    for (const id of arrayOfIds){
        if (id !== nextId){
            break
        }
        nextId++
    }
    return nextId
}

function getAllIdOf(list) {
    let arrayOfIds = []
    for (const menuItem of list){
        arrayOfIds.push(menuItem.id)
        if (menuItem.children && menuItem.children.length > 0){
            let children = menuItem.children
            for (const child of children){
                if (!arrayOfIds.includes(child.id)){
                    arrayOfIds.push(child.id)
                }
                if (!child.children || child.children.length <= 0){
                    continue
                }
                const result = getAllIdOf(child.children)

                for (const id of result){
                    if (!arrayOfIds.includes(id)){
                        arrayOfIds.push(id)
                    }
                }
            }
        }
    }
    return arrayOfIds
}
// End get next id

// Replace annoying characters
function replaceQuoteAndPercent(string){
    while (string.includes("\\'") || string.includes("%")) {
        string = string
            .replace("\\'", quotePattern)
            .replace("%", percentPattern);
    }
    return string
}

function putQuoteAndPercent(string){
    while (string.includes(quotePattern) || string.includes(percentPattern)) {
        string = string
            .replace(quotePattern, "'")
            .replace(percentPattern, "%");
    }
    return string
}
function replaceAllQuotesAndPercent(MenuList){
    for (const val of MenuList){
        replaceAllQuotesAndPercentRec(val)
    }
}

function replaceAllQuotesAndPercentRec(MenuList){
    for (const [lang, title] of Object.entries(MenuList.title)){
        MenuList.title[lang] = putQuoteAndPercent(title)
    }
    
    if (!MenuList.children || MenuList.children.length <= 0){
        return
    }
    for (let child of MenuList.children){
        replaceAllQuotesAndPercentRec(child)
    }
}
// End replace annoying characters

// Add menu
function addMenu() {
    const menuName = document.getElementById('menuName').value;
    const errorMessageEmpty = document.getElementById('error-message-empty');
    const errorMessageBackQuote = document.getElementById('error-message-back-quote');

    if (menuName.trim().length === 0) {
        errorMessageEmpty.style.display = 'block';
        errorMessageBackQuote.style.display = 'none';
    } else if (menuName.includes("`")) {
        errorMessageEmpty.style.display = 'none';
        errorMessageBackQuote.style.display = 'block';
    } else {
        $('#ConfirmAddMenu').modal('hide');
        errorMessageEmpty.style.display = 'none';
        errorMessageBackQuote.style.display = 'none';
        document.getElementById('addMenuForm').submit();
    }
}

function addInList(id, item, list) {
    list = list.map(function(element) {
        if (element.id === id) {
            if (!Array.isArray(element.children)) {
                element.children = []
            }
            element.children.push(item)
        }
        if (element.children && element.children.length > 0) {
            element.children = addInList(id, item, element.children)
        }
        return element;
    })
    return list
}

function addCustomMenuItem(element, id="0") {
    const form = element.form
    if (!isValid(form)) {
        return
    }
    
    let [menuItemName, menuItemType, menuItemUrl] = getFormItems(form)
    let menu = findMenuInList(id, MENU_LIST)
    let depthToAdd = 0
    if (menu !== null) {
        depthToAdd = menu.depth + 1
    }
    let newItem = {
        id: getNextId(),
        title: {},
        type: menuItemType,
        url: {},
        depth: depthToAdd,
        children: []
    };
    newItem.title[LOCALE] = menuItemName
    console.log(menuItemType)
    if (menuItemType.toLowerCase() !== "url") {
        newItem.url["en_US"] = menuItemUrl
    }
    else {
        newItem.url[LOCALE] = menuItemUrl
    }

    let newMenu = generateMenuRecursive(newItem)
    if (menu === null) {
        document.getElementById('menu-item-list').innerHTML += newMenu;
        MENU_LIST.push(newItem);
    } else {
        let childNodes = document.getElementById(id).parentNode.childNodes;
        let ulElement = null;
        for (let i = 0; i < childNodes.length; i++) {
            if (childNodes[i].nodeType === Node.ELEMENT_NODE && childNodes[i].classList.contains('menu-item')) {
                ulElement = childNodes[i];
                break;
            }
        }
        if (ulElement) {
            ulElement.innerHTML += newMenu;
            MENU_LIST = addInList(id, newItem, MENU_LIST);

            // Add sub-menu icon if not already present
            let parentElement = document.getElementById(id).parentNode;
            let titleContainer = parentElement.querySelector('.title-container');
            if (titleContainer && !titleContainer.querySelector('.tree-icon')) {
                let titleSpan = titleContainer.querySelector('[data-id="titleSpan"]');
                if (titleSpan) {
                    titleSpan.insertAdjacentHTML('afterend', '<span> <i class="fas fa-caret-down tree-icon"></i></span>');
                }
            }

            // Open the parent menu
            const treeIcon = parentElement.querySelector('.tree-icon');
            const childrenUl = parentElement.querySelector('ul');
            if (treeIcon === null){
                return
            }
            if (childrenUl) {
                childrenUl.style.display = 'block'
                treeIcon.classList.remove('fa-caret-up');
                treeIcon.classList.add('fa-caret-down');
            }
        }
    }
    deleteEditField(form.id);
    generatePreviewMenus();
    updateArrowStyles();

    closeClosestModal(form);
}
// End add menu

// Find menu
function findMenuInList(id, list) {
    for (const menuItem of list){
        if (menuItem.id === id){
            return menuItem
        }
        if (menuItem.children && menuItem.children.length > 0){
            let children = menuItem.children
            for (const child of children){
                let result = findMenuInList(id, children)
                if (result){
                    return result
                }
            }
        }
    }
    return null
}
// End find menu

// Edit menu
function changeParameters(id) {
    const form = document.getElementById('editMenuItemForm')
    if (!isValid(form)) {
        return
    }

    const [title, type, url] = getFormItems(form)
    const menuItem = document.getElementById(id).parentElement
    if (menuItem === null) {
        console.error("The id given in changeParameters parameter doesn't exist")
        return
    }
    
    saveTitleTypeAndUrl(id, title, type, url)

    const titleSpan = menuItem.querySelector('[data-id="titleSpan"]')
    titleSpan.textContent = getValueByLocaleOf(findMenuInList(id, MENU_LIST).title)

    deleteEditField('editMenuItemForm')
    generatePreviewMenus()

    closeClosestModal(form);
}

function getFormItems(form) {
    let menuItemName = form.elements['menuItemName'].value.trim()
    if (menuItemName === null || menuItemName === '') {
        menuItemName = 'New menu item'
    }
    let menuItemType = form.elements['menuType'].value.trim()
    if (menuItemType === null || menuItemType === '') {
        menuItemType = 'url'
    }

    let selectedKey
    if (menuItemType.toLowerCase() !== "url") {
         selectedKey = form.elements['menuItemId'].value;
    }
    else{
        selectedKey = form.elements['menuItemUrl'].value;
    }
    if (selectedKey === null) {
        selectedKey = ''
    }
    return [menuItemName, menuItemType, selectedKey]
}
// End edit menu

// Delete menu
function deleteMenuItem(id) {
    let elementToRemove = document.getElementById(id).parentElement;
    
    let parentInMenuList = findParentOf(id, MENU_LIST)
    if (parentInMenuList[1].children && parentInMenuList[1].children.length === 1) {
        const parentInHtml = elementToRemove.parentElement.parentElement;
        const treeIcon = parentInHtml.querySelector('.tree-icon')
        treeIcon.classList.remove('fa-caret-up')
        treeIcon.classList.remove('fa-caret-down')
    }
    if (elementToRemove) {
        if (elementToRemove.remove) {
            elementToRemove.remove()
        } else {
            elementToRemove.parentNode.removeChild(elementToRemove)
        }
        MENU_LIST = deleteFromList(id, MENU_LIST)
        generatePreviewMenus()
    } else {
        console.error("The id doesn't exist")
    }
}

function deleteFromList(id, list) {
    list = list.filter(function(element) {
        if (element.children && element.children.length > 0) {
            element.children = deleteFromList(id, element.children)
        }
        return element.id !== id;
    })
    return list
}

function deleteMenu() {
    document.getElementById('deleteForm').submit();
}
// End delete menu

// Validation
function isValid(form) {
    const menuItemName = form.elements['menuItemName'].value.trim()
    const menuItemType = form.elements['menuType'].value.trim()
    const errorType = form.getElementsByClassName("error")[0]
    const errorMessageTitle = form.querySelector('#error-message-title');
    const errorMessageUrl = form.querySelector('#error-message-url');

    let menuItemUrl
    errorType.innerText = ""
    if (menuItemType === ""){
        errorType.innerText = "Choose a category"
        return false
    }
    else if (!(menuItemType in loopsDictionary) && menuItemType !== "url"){
        errorType.innerText = "Invalid selection"
        return false
    }
    else if (menuItemType === "url"){
        menuItemUrl = form.elements['menuItemUrl'].value.trim()
    }
    else{
        menuItemUrl = form.elements['menuItemId'].value.trim()
    }

    if (menuItemUrl === "" || menuItemUrl === null || menuItemUrl === undefined){
        errorType.innerText = "A value must be filled"
        return false
    }
    else if (menuItemType !== "url"){
        let found = false
        for (const [key, value] of Object.entries(loopsDictionary[menuItemType])){
            if (value.title + "-" + value.id === menuItemUrl){
                found = true
                break
            }
        }
        if (!found){
            errorType.innerText = "Invalid " + menuItemType
            return false
        }
    }

    
    if (menuItemName.includes("`")) {
        errorMessageTitle.style.display = 'block';
        return false
    } else {
        errorMessageTitle.style.display = 'none';
    }

    if (menuItemUrl.includes("`")) {
        errorMessageUrl.style.display = 'block';
        return false
    } else {
        errorMessageUrl.style.display = 'none';
    }

    return true
}
// End validation

// Save data
function saveData() {
    allowUnload = true
    document.getElementById('menuData').value = JSON.stringify(MENU_LIST)
    document.getElementById('menuDataId').value = JSON.stringify(CURRENT_SELECTED_MENU_ID)
    document.getElementById('savedData').submit()
}

function saveMenuItemName() {
    const editForm = document.getElementById('editMenuItemForm')
    if (!isValid(editForm)) {
        return
    }

    const modifiedLocal = selectedLanguage ? selectedLanguage : LOCALE;
    menuToModify = findMenuInList(CURRENT_ID, MENU_LIST)
    
    if (menuToModify === null) {
        console.error("The id given in saveMenuItemName doesn't exist")
        return
    }
    
    menuToModify.title[modifiedLocal] = editForm["menuItemName"].value;

    const titleSpan = document.getElementById(getCurrentId()).parentElement.querySelector('[data-id="titleSpan"]')
    titleSpan.textContent = getValueByLocaleOf(findMenuInList(getCurrentId(), MENU_LIST).title)

    generatePreviewMenus()
}

function saveMenuItemUrl() {
    const editForm = document.getElementById('editMenuItemForm')
    if (!isValid(editForm)) {
        return
    }

    const modifiedLocal = selectedLanguage ? selectedLanguage : LOCALE;
    menuToModify = findMenuInList(CURRENT_ID, MENU_LIST)
    
    if (menuToModify === null) {
        console.error("The id given in saveMenuItemUrl doesn't exist")
        return
    }

    menuToModify.url[modifiedLocal] = editForm["menuItemUrl"].value;
}

function saveTitleTypeAndUrl(id, title, type, url) {
    const modifiedLocal = selectedLanguage ? selectedLanguage : LOCALE;
    const menuToModify = findMenuInList(id, MENU_LIST)

    if (menuToModify === null) {
        console.error("The id given in saveTitleTypeAndUrl doesn't exist")
        return
    }

    menuToModify.title[modifiedLocal] = title
    menuToModify.url[modifiedLocal] = url
}
// End save data

// Form edit field
function setEditFields(id) {
    const element = findMenuInList(id, MENU_LIST)
    if (element === null) {
        console.error("The id given in setEditField doesn't exist")
        return
    }
    const form = document.getElementById('editMenuItemForm')
    CURRENT_ID = id
    form.elements['menuItemName'].value = getValueByLocaleOf(element.title)
    form.elements['menuItemUrl'].value = getValueByLocaleOf(element.url)
}

function deleteEditField(formId) {
    const form = document.getElementById(formId)
    form.elements['menuItemName'].value = ""
    form.elements['menuItemUrl'].value = ""
}
// End form edit field

// Generate menu
function generateSelect(list) {
    let menu = document.getElementById('selectMenuName')
    menu.innerHTML = ""
    for (const menuName of list){
        let option = document.createElement('option');
        option.text = menuName.title;
        option.id = menuName.id;
        if (option.id === "menu-selected-" + CURRENT_SELECTED_MENU_ID) {
            option.selected = true;
        }
        menu.appendChild(option);
    }
}

function generateMenu(list) {
    let menu = document.getElementById('menu-item-list')
    menu.innerHTML = ""
    for (const menuItem of list){
        menu.innerHTML += generateMenuRecursive(menuItem)
    }
    updateArrowStyles();
}

function generateMenuRecursive(menuItem){
    let depth = "zero-depth"
    if (menuItem.depth !== 0){
        if (menuItem.depth%2 === 0){
            depth = "even-depth"
        }
        else {
            depth = ""
        }
    }

    let children = ""
    if (menuItem.children && menuItem.children.length > 0){
        for (const child of menuItem.children){
            children += generateMenuRecursive(child)
        }
    }

    let arrowSpan = ""
    if (children !== "") {
        arrowSpan = `<span> <i class="fas fa-caret-down tree-icon"></i></span>`;
    }

    let newMenu = `
    <li draggable="true" ondragstart="drag(event)" ondrop="drop(event)" ondragover="allowDrop(event)">
        <div class="item `+depth+`" id="`+menuItem.id+`" onclick="toggleChildren(this,event)">
            <a class="drag-and-drop-icon">
                <i class="fas fa-bars"></i>
            </a>
            <div class="title-container">
                <span data-id="titleSpan">`+ getValueByLocaleOf(menuItem.title) +`</span>` + arrowSpan + `
            </div>
            <div class="btn-group priority-over-drop-and-down">
                <a title="Edit this item" class="btn btn-info btn-responsive" data-toggle="modal" data-target="#EditMenu" onclick="setEditFields(`+menuItem.id+`)">
                    <i class="glyphicon glyphicon-edit"></i>
                </a>
                <a title="Add a new child" class="btn btn-primary btn-responsive action-btn" data-toggle="modal" data-target="#AddAndEditSecondaryMenu" onclick="setCurrentId(`+menuItem.id+`)">
                    <i class="glyphicon glyphicon-plus-sign"></i>
                </a>
                <a title="Delete this item" class="btn btn-danger btn-responsive module-delete-action" data-toggle="modal" data-target="#DeleteMenu" onclick="setCurrentId(`+menuItem.id+`)">
                    <i class="glyphicon glyphicon-trash"></i>
                </a>
            </div>
            <span class="arrows  priority-over-drop-and-down">
                <a class="leftArrow"  onclick="moveMenuUp(`+menuItem.id+`)"><i class="glyphicon glyphicon-arrow-up" title="move menu above"></i></a>
                <a class="rightArrow"  onclick="moveMenuDown(`+menuItem.id+`)"><i class="glyphicon glyphicon-arrow-down" title="move menu below"></i></a>
            </span>
        </div>
        <ul class="menu-item" style="`+ ((children) ? "display: block;" : "display: none;") +`">
            `+children+`
        </ul>
    </li>`

    updateArrowStyles();
    return newMenu;
}
// End generate menu

// Move menu
function moveMenuUp(id) {
    menuToMove = document.getElementById(id).parentNode
    if (menuToMove.previousElementSibling){
        menuToMove.parentElement.insertBefore(menuToMove, menuToMove.previousElementSibling)
    }

    MENU_LIST = moveMenuUpInList(id, MENU_LIST)
    generatePreviewMenus()
    updateArrowStyles();
}


function moveMenuDown(id) {
    menuToMove = document.getElementById(id).parentElement
    if (menuToMove.nextElementSibling){
        menuToMove.parentElement.insertBefore(menuToMove.nextElementSibling, menuToMove)
    }

    MENU_LIST = moveMenuDownInList(id, MENU_LIST)
    generatePreviewMenus()
    updateArrowStyles();
}

function moveMenuUpInList(id, list) { // recursive
    for (let i = 0; i < list.length; i++){
        if (list[i].id === id){
            if (i > 0){
                let temp = list[i]
                list[i] = list[i-1]
                list[i-1] = temp
            }
            return list
        }
        if (list[i].children && list[i].children.length > 0){
            list[i].children = moveMenuUpInList(id, list[i].children)
        }
    }
    return list
}

function moveMenuDownInList(id, list) {
    for (let i = 0; i < list.length; i++){
        if (list[i].id === id){
            if (i < list.length - 1){
                let temp = list[i]
                list[i] = list[i+1]
                list[i+1] = temp
            }
            return list
        }
        if (list[i].children && list[i].children.length > 0){
            list[i].children = moveMenuDownInList(id, list[i].children)
        }
    }
    return list
}

function updateArrowStyles() {
    const ulItems = document.querySelectorAll('.menu-item');

    ulItems.forEach((ul) => {
        const liItems = ul.querySelectorAll(':scope > li');
        liItems.forEach((li, index) => {
            const upArrow = li.querySelector('.leftArrow i');
            const downArrow = li.querySelector('.rightArrow i');

            if (upArrow) {
                upArrow.classList.remove('end-arrow');
            }
            if (downArrow) {
                downArrow.classList.remove('end-arrow');
            }

            if (index === 0 && upArrow) {
                upArrow.classList.add('end-arrow');
            }
            if (index === liItems.length - 1 && downArrow) {
                downArrow.classList.add('end-arrow');
            }
        });
    });
}
// End move menu

// Drop down
function toggleTopLevelVisibility() {
    const button = document.getElementById('toggle-all-children');
    const isVisible = (buttonState === 'hide');

    MENU_LIST.forEach(item => {
        if (item.depth === 0) {
            const itemId = item.id.toString();
            const listItem = document.getElementById(itemId);
            if (listItem) {
                const childrenUl = listItem.parentElement.querySelector('ul');
                if (childrenUl && childrenUl.tagName === 'UL') {
                    childrenUl.style.display = isVisible ? 'none' : 'block';
                    const treeIcon = listItem.querySelector('.tree-icon');
                    if (treeIcon) {
                        treeIcon.classList.toggle('fa-caret-down', !isVisible);
                        treeIcon.classList.toggle('fa-caret-up', isVisible);
                    }
                }
            }
        }
    });
    buttonState = isVisible ? 'show' : 'hide';
    button.textContent = isVisible ? translations.showAllChildren : translations.hideAllChildren;
}

function toggleChildren(span, event) {

    if (event.target.closest('.priority-over-drop-and-down')) {
        return;
    }

    const listItem = span.closest('.item').parentElement;
    const treeIcon = listItem.querySelector('.tree-icon');
    const childrenUl = listItem.querySelector('ul');

    if (treeIcon === null){
        return
    }

    if (childrenUl) {
        childrenUl.style.display = childrenUl.style.display === 'none' ? 'block' : 'none';
        if (childrenUl.style.display === 'none') {
            treeIcon.classList.remove('fa-caret-down');
            treeIcon.classList.add('fa-caret-up');
        } else {
            treeIcon.classList.remove('fa-caret-up');
            treeIcon.classList.add('fa-caret-down');
        }
    }
}

function toggleFlags() {
    var flagsList = document.getElementById('flags-list');
    if (flagsList.style.display === 'none') {
        flagsList.style.display = 'block';
    } else {
        flagsList.style.display = 'none';
    }
}

function selectLanguage(languageElement) {
    selectedLanguage = languageElement.getAttribute('data-locale');
    currentMenu = findMenuInList(CURRENT_ID, MENU_LIST)
    document.forms["editMenuItemForm"]["menuItemName"].value = getValueByLocaleOf(currentMenu.title, selectedLanguage)
    document.forms["editMenuItemForm"]["menuItemUrl"].value = getValueByLocaleOf(currentMenu.url, selectedLanguage)
    document.getElementById('selectedLanguageBtn').innerText = selectedLanguage;
    toggleFlags();
}
// End drop down

// Drag and drop
function drag(ev) {
    ev.dataTransfer.setData("text/plain", ev.target.children[0].id);
}

function drop(ev) {
    ev.stopPropagation();
    ev.preventDefault();

    document.querySelector('.drop-indicator').style.display = 'none'

    var data = ev.dataTransfer.getData("text/plain");
    var draggedItemId = parseInt(data);

    var draggedItem = findMenuInList(draggedItemId, MENU_LIST);

    if (draggedItem) {
        var targetItemId = parseInt(ev.target.closest(".item").id)

        var rect = ev.target.closest("div.item").getBoundingClientRect()
        var mouseY = ev.clientY - rect.top;
        var mouseX = ev.clientX - rect.left;

        const insertionBefore = mouseY < rect.height / 2
        const insertAsChild = !insertionBefore && mouseX > rect.width / 6

        // inserts the moved element before or after the target element, depending on the drop position
        const problems = insertMenuItem(draggedItemId, targetItemId, insertionBefore, insertAsChild)

        if (problems === 0){
            console.log("success")
        }
        else if (problems === 1){
            console.log("OSKOUR: element not found in list")
        }
        else if (problems === 2) {
            console.log("is parent")
        }
        else if (problems === 3) {
            console.log("same element")
        }

        generateMenu(MENU_LIST)

        generatePreviewMenus();

        const button = document.getElementById('toggle-all-children');
        const isVisible = false;
        buttonState = isVisible ? 'show' : 'hide';
        button.textContent = isVisible ? translations.showAllChildren : translations.hideAllChildren;

    } else {
        console.error("L'élément avec l'ID", draggedItemId, "n'a pas été trouvé dans MENU_LIST.");
    }
}

function insertMenuItem(draggedItemId, positionToInsert, insertionBefore, insertAsChild) {
    if (draggedItemId === positionToInsert) {
        return 3
    }

    if (draggedItemId >= 0) {
        if (isParentOf(draggedItemId, positionToInsert)){
            let [root, parentOfDragged] = findParentOf(draggedItemId, MENU_LIST)
            if (root === 0){
                parentOfDragged = MENU_LIST
            }
            if (!parentOfDragged){
                return 1
            }

            let draggedItem = findMenuInList(draggedItemId, MENU_LIST)
            while (draggedItem.children.length > 0){
                let popedChild = draggedItem.children.pop()
                if (root === 0){
                    MENU_LIST.splice(MENU_LIST.indexOf(draggedItem)+1, 0, popedChild)
                }
                else {
                    parentOfDragged.children.splice(parentOfDragged.children.indexOf(draggedItem)+1, 0, popedChild)
                }
                popedChild.depth = (root === 0) ? 0 : parentOfDragged.depth + 1
                updateDepth(popedChild, popedChild.depth)
            }
        }
        if (insertAsChild){
            let newParent = findMenuInList(positionToInsert, MENU_LIST)
            if (newParent === null){
                return 1
            }

            const draggedItem = popFromMenuList(draggedItemId, MENU_LIST)

            if (newParent.children == null){
                newParent.children = [draggedItem]
            }
            else {
                newParent.children.push(draggedItem)
            }
            draggedItem.depth = newParent.depth + 1
            updateDepth(draggedItem, draggedItem.depth)
            return 0
        }
            
        let [root, parent] = findParentOf(positionToInsert, MENU_LIST)

        let menuToMove = popFromMenuList(draggedItemId, MENU_LIST)
        if (menuToMove == null){
            return 1
        }
        if (root === 0){
            insertionBefore ? MENU_LIST.splice(MENU_LIST.indexOf(parent), 0, menuToMove) : MENU_LIST.splice(MENU_LIST.indexOf(parent)+1, 0, menuToMove)
            menuToMove.depth = 0
        }
        else {
            insertionBefore ? parent.children.splice(parent.children.indexOf(findMenuInList(positionToInsert, MENU_LIST)), 0, menuToMove) : parent.children.splice(parent.children.indexOf(findMenuInList(positionToInsert, MENU_LIST))+1, 0, menuToMove)
            menuToMove.depth = parent.depth + 1
        }

        updateDepth(menuToMove, menuToMove.depth)
        return 0
    }
    return null
}

function isParentOf(parent, child){
    let parentElement = findMenuInList(parent, MENU_LIST)
    if (parentElement.children && parentElement.children.length > 0){
        for (const childElement of parentElement.children){
            if (childElement.id === child || isParentOf(childElement.id, child)) {
                return true
            }
        }
    }
    return false
}

function updateDepth(menuItem, depth){
    if (menuItem.children && menuItem.children.length > 0){
        for (const child of menuItem.children){
            child.depth = depth + 1
            updateDepth(child, child.depth)
        }
    }
}

function findParentOf(id, list) {
    for (const menuItem of list) {
        if (menuItem.id === id) {
            return [0, menuItem]
        }
        if (menuItem.children && menuItem.children.length > 0){
            let children = menuItem.children
            for (const _ of children){
                let result = findParentOf(id, children)
                if (result){
                    if (result[0] === 0){
                        return [1, menuItem]
                    }
                    return result
                }
            }
        }
    }
    return null
}

function popFromMenuList(id, list){
    for (const menuItem of list){
        if (menuItem.id === id){
            if (menuItem.depth < 1){
                return list.splice(list.indexOf(menuItem), 1)[0]
            }
            return menuItem
        }
        if (menuItem.children && menuItem.children.length > 0){
            let children = menuItem.children
            for (const child of children){
                let result = popFromMenuList(id, children)
                if (result) {
                    if (children.indexOf(result) !== -1){
                        return children.splice(children.indexOf(result), 1)[0]
                    }
                    return result
                }
            }
        }
    }
    return null
}

function findMenuItemById(itemId) {
    return MENU_LIST.find(item => item.id === itemId);
}

function allowDrop(ev) {
    ev.preventDefault();
    const dropIndicator = document.querySelector('.drop-indicator');

    try{
        // retrieve mouse position relative to the target element
        const rect = ev.target.closest("div.item").getBoundingClientRect();
        const mouseY = ev.clientY - rect.top;
        const mouseX = ev.clientX - rect.left;

        const targetItem = ev.target.closest(".item").parentElement;
        // display bar above or below the target element

        dropIndicator.style.left = targetItem.offsetLeft + 'px'; // positions the bar to the left of the target element
        dropIndicator.style.width = targetItem.offsetWidth + 'px'; // adjust bar width to that of the target elem

        if (mouseY < rect.height / 2) { // if the mouse is over the target element
            dropIndicator.style.top = targetItem.offsetTop + 'px'; // position the bar above the target element
        } else { // if the mouse is below the target element => positions bar below
            dropIndicator.style.top = (targetItem.offsetTop + targetItem.offsetHeight) + 'px';
            if (mouseX > rect.width / 6) { // if the mouse is to the right of the target element
                dropIndicator.style.left = (targetItem.offsetLeft + targetItem.offsetWidth * 0.04) + 'px';
                dropIndicator.style.width = (targetItem.offsetWidth * 0.96) + 'px';
            }
        }
        dropIndicator.style.display = 'block';
    }
    catch{
        dropIndicator.style.display = 'none';
    }
}
// End drag and drop

// Preview
function generatePreviewMenus() {
    const previewUl = document.getElementById('menus')
    previewUl.innerHTML = ""
    for (const menuItem of MENU_LIST){
        previewUl.innerHTML += generatePreviewMenuRecursive(menuItem, 1)
    }
}

function generatePreviewMenuRecursive(menuItem){
    let children = ""
    if (menuItem.children && menuItem.children.length > 0){
        for (const child of menuItem.children){
            children += generatePreviewMenuRecursive(child)
        }
    }
    let classes = (menuItem.depth >= 1) ? "parent deep" : "parent"
    return `
        <li>
            <a>` + getValueByLocaleOf(menuItem.title) + `</a>
            <ul class="` + classes + `">
                ` + children + `
            </ul>
        </li>
    `;
}
// End Preview

// Search product
function searchProducts(query, formId) {
    const matchingProducts = document.querySelector(`#${formId} ~ ul`);
    matchingProducts.innerHTML = '';

    if (query.trim() === '') return;

    const filteredProducts = products.filter(product =>
        product.title.toLowerCase().includes(query.toLowerCase())
    );

    filteredProducts.forEach(product => {
        const li = document.createElement('li');
        li.textContent = `${product.title} (${product.ref})`;
        li.addEventListener('click', () => {
            document.querySelector(`#${formId} input[name="menuItemUrl"]`).value = product.url;
        });
        matchingProducts.appendChild(li);
    });
}

function addOptionsOfSelectedCategory() {
    const menuTypeSelect = document.getElementsByClassName('menuType');
    for (const category of menuTypeSelect){
        for (const key in loopsDictionary) {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = key;
            category.appendChild(option);
        }
    }
}

function updateDataList(selectedKey, parentDiv) {
    const dataList = parentDiv.querySelector('.itemList');
    dataList.innerHTML = "";

    if (loopsDictionary[selectedKey]) {
        loopsDictionary[selectedKey].forEach(item => {
            const option = document.createElement('option');
            option.value = `${item.title}-${item.id}`;
            dataList.appendChild(option);
        });
    }
}

function updateInputOrDatalist(selectElement) {
    const selectedKey = selectElement.value;
    const parentDiv = selectElement.closest('.edit-modal-line');
    const inputElement = selectElement.form['menuItemId']
    const urlInputElement = selectElement.form['menuItemUrl']
    const datalistElement = parentDiv.querySelector('.itemList');

    if (selectedKey === "") {
        inputElement.style.display = "none";
        urlInputElement.style.display = "none"
        datalistElement.style.display = "none";
    } else if (selectedKey === "url") {
        urlInputElement.style.display = "block";
        inputElement.style.display = "none";
        datalistElement.style.display = "none";
        inputElement.value = "";
    } else {
        urlInputElement.style.display = "none"
        inputElement.style.display = "block";
        inputElement.value = "";
        updateDataList(selectedKey, parentDiv);
    }
}
// End search product

// Flashes

// Function to remove flash messages from the DOM
function removeFlashMessages() {
    const flashMessages = document.getElementsByClassName('alert-flash-to-delete')
    Array.from(flashMessages).forEach(function(message) {
        message.remove()
    });
}

// Function to notify server to clear flash messages
function clearFlashMessagesOnServer() {
    let xhr = new XMLHttpRequest()
    xhr.open('GET', '/admin/module/CustomFrontMenu/clearFlashes', true)

    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status !== 200) {
                console.error('Network response was not ok:', xhr.statusText)
            }
        }
    };

    xhr.onerror = function () {
        console.error('Error:', xhr.statusText);
    }

    xhr.send()
}

// End flashes

// Event Listener
window.addEventListener('beforeunload', function(event) {
    if (!allowUnload) {
        event.preventDefault();
    }
}, { capture: true });

document.getElementById('selectMenuName').addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];

    document.getElementById('menuId').value = selectedOption.id;
    document.getElementById('askedMenu').submit();
});
// End Event Listener

// Initialization
window.onload = function() {

    // Get data
    MENU_NAMES = getFromJson(menuNames)
    MENU_LIST = getFromJson(menuItems)
    replaceAllQuotesAndPercent(MENU_LIST)
    for (let menu of MENU_NAMES){
        menu.title = putQuoteAndPercent(menu.title)
    }

    // Generate elements
    generateSelect(MENU_NAMES)
    generateMenu(MENU_LIST)
    generatePreviewMenus()
    document.getElementById('deleteForm').elements['menuNameToDelete'].value = CURRENT_SELECTED_MENU_ID

    // Disable buttons if there aren't any menu
    if (CURRENT_SELECTED_MENU_ID === 'undefined' || CURRENT_SELECTED_MENU_ID === -1 || isNaN(CURRENT_SELECTED_MENU_ID)) {
        let listToDelete = Array.from(document.getElementsByClassName('delete-if-no-menu'))
        listToDelete.forEach(function (elementToDelete) {
            elementToDelete.disabled = true
        })
    }

    // Manage flashes
    if (document.getElementsByClassName('alert-flash-to-delete').length > 0) {
        clearFlashMessagesOnServer()
        document.addEventListener('click', function() {
                removeFlashMessages()
        })
    }

    addOptionsOfSelectedCategory();
}
// End Initialization