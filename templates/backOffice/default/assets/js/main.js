var MENU_LIST
var MENU_NAMES
let CURRENT_ID = null

function getCurrentId() {
    if (CURRENT_ID === null) {
        console.error("CURRENT_ID not set")
    }
    return CURRENT_ID
}

function setCurrentId(id) {
    CURRENT_ID = id
}

function searchInMenuList(id, title, url, list) {
    list = list.map(function(element) {
        if (element.id === id) {
            element.title = title
            element.url = url
        }
        if (element.childrens && element.childrens.length > 0) {
            element.childrens = searchInMenuList(id, title, url, element.childrens)
        }
        return element;
    })
    return list
}

function changeParameters(id) {
    let [title, url] = getFormItems('editMenuItemForm')
    let menuItem = document.getElementById(id).parentElement
    if (menuItem === null) {
        console.error("The id given in changeParameters parameter doesn't exist")
        return
    }
    let titleSpan = menuItem.querySelector('[data-id="titleSpan"]')
    titleSpan.textContent = title
    MENU_LIST = searchInMenuList(id, title, url, MENU_LIST)
    deleteFormField('editMenuItemForm')
    generatePreviewMenus()
}

function getFormItems(formId) {
    let form = document.getElementById(formId)
    let menuItemName = form.elements['menuItemName'].value.trim()
    if (menuItemName === null || menuItemName === '') {
        menuItemName = 'New menu item'
    }
    let menuItemUrl = form.elements['menuItemUrl'].value.trim()
    if (menuItemUrl === null) {
        menuItemUrl = ''
    }
    return [menuItemName, menuItemUrl]
}

function setEditFields(id) {
    let element = findMenuInList(id, MENU_LIST)
    if (element === null) {
        console.error("The id given in setEditField doesn't exist")
        return
    }
    let form = document.getElementById('editMenuItemForm')
    form.elements['menuItemName'].value = element.title
    form.elements['menuItemUrl'].value = element.url
}

function addCustomMenuItem(form, id="0") {
    let [menuItemName, menuItemUrl] = getFormItems(form);
    let element = findMenuInList(id, MENU_LIST);
    let depthToAdd = 0;
    if (element !== null) {
        depthToAdd = element.depth + 1;
    }
    let newItem = {
        id: getNextId(),
        title: menuItemName,
        url: menuItemUrl,
        depth: depthToAdd,
        childrens: []
    };
    let newMenu = generateMenuRecursive(newItem);
    if (element === null) {
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

            // Ajout de l'icône de sous-menu si elle n'existe pas déjà
            let parentElement = document.getElementById(id).parentNode;
            let titleContainer = parentElement.querySelector('.title-container');
            if (titleContainer && !titleContainer.querySelector('.tree-icon')) {
                let titleSpan = titleContainer.querySelector('[data-id="titleSpan"]');
                if (titleSpan) {
                    titleSpan.insertAdjacentHTML('afterend', '<span> <i class="fas fa-caret-down tree-icon"></i></span>');
                }
            }
        }
    }
    deleteFormField(form);
    generatePreviewMenus();
    updateArrowStyles();
}



function deleteFormField(formId) {
    let form = document.getElementById(formId)
    form.elements['menuItemName'].value = null
    form.elements['menuItemUrl'].value = null
}

function getMenuList(){
    const jsonMenuList = decodeURIComponent(document.cookie
        .split("; ")
        .find((row) => row.startsWith("menuItems="))
        ?.split("=")[1]);
    return JSON.parse(jsonMenuList);
}

function getMenuNames(){
    const jsonMenuNames = decodeURIComponent(document.cookie
        .split("; ")
        .find((row) => row.startsWith("menuNames="))
        ?.split("=")[1]);
    return JSON.parse(jsonMenuNames);
}

function deleteMenuItem(id) {
    let elementToRemove = document.getElementById(id).parentElement;
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
        if (element.childrens && element.childrens.length > 0) {
            element.childrens = deleteFromList(id, element.childrens)
        }
        return element.id !== id;
    })
    return list
}

function addInList(id, item, list) {
    list = list.map(function(element) {
        if (element.id === id) {
            if (!Array.isArray(element.childrens)) {
                element.childrens = []
            }
            element.childrens.push(item)
        }
        if (element.childrens && element.childrens.length > 0) {
            element.childrens = addInList(id, item, element.childrens)
        }
        return element;
    })
    return list
}

function generateMenuRecursive(menuItem){
    let depth = "zero-depth"
    if (menuItem.depth != 0){
        if (menuItem.depth%2 == 0){
            depth = "even-depth"
        }
        else {
            depth = ""
        }
    }

    let childrens = ""
    if (menuItem.childrens && menuItem.childrens.length > 0){
        for (const child of menuItem.childrens){
            childrens += generateMenuRecursive(child)
        }
    }

    let arrowSpan = ""
    if (childrens !== "") {
        arrowSpan = `<span> <i class="fas fa-caret-down tree-icon"></i></span>`;
    }

    let newMenu = `
    <li draggable="true" ondragstart="drag(event)" ondrop="drop(event)" ondragover="allowDrop(event)">
        <div class="item `+depth+`" id="`+menuItem.id+`" onclick="toggleChildren(this,event)">
            <a class="drag-and-drop-icon">
                <i class="fas fa-bars"></i>
            </a>
            <div class="title-container">
                <span data-id="titleSpan">`+menuItem.title+`</span>` + arrowSpan + `
            </div>
            <div class="btn-group priority-over-drop-and-down">
                <a title="Edit this item" class="btn btn-info btn-responsive" data-toggle="modal" data-target="#EditMenu" onclick="setCurrentId(`+menuItem.id+`); setEditFields(getCurrentId())">
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
                <a class="leftArrow"  onclick="moveMenuUp(`+menuItem.id+`)"><i class="glyphicon glyphicon-arrow-up"></i></a>
                <a class="rightArrow"  onclick="moveMenuDown(`+menuItem.id+`)"><i class="glyphicon glyphicon-arrow-down"></i></a>
            </span>
        </div>
        <ul class="menu-item" style="`+ ((childrens) ? "display: block;" : "display: none;") +`">
            `+childrens+`
        </ul>
    </li>`

    updateArrowStyles();
    return newMenu;
}

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

function findMenuInList(id, list) {
    for (const menuItem of list){
        if (menuItem.id === id){
            return menuItem
        }
        if (menuItem.childrens && menuItem.childrens.length > 0){
            let childrens = menuItem.childrens
            for (const child of childrens){
                let result = findMenuInList(id, childrens)
                if (result){
                    return result
                }
            }
        }
    }
    return null
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
        if (list[i].childrens && list[i].childrens.length > 0){
            list[i].childrens = moveMenuUpInList(id, list[i].childrens)
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
        if (list[i].childrens && list[i].childrens.length > 0){
            list[i].childrens = moveMenuDownInList(id, list[i].childrens)
        }
    }
    return list
}

function generateSelect(list) {
    let menu = document.getElementById('selectMenuName')
    menu.innerHTML = ""
    for (const menuName of list){
        let option = document.createElement('option');
        option.text = menuName;
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

function getNextId() {
    let nextId = 1
    let arrayOfIds = getAllIdOf(MENU_LIST)
    arrayOfIds.sort((a, b) => a - b)
    for (const id of arrayOfIds){
        if (id != nextId){
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
        if (menuItem.childrens && menuItem.childrens.length > 0){
            let childrens = menuItem.childrens
            for (const child of childrens){
                let result = getAllIdOf(childrens)
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

// ------------------------------ Begin drop down ------------------------------

function toggleTopLevelVisibility() {
    const button = document.getElementById('toggle-all-childrens');
    const isVisible = button.textContent === 'Hide all children';

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
    button.textContent = isVisible ? 'Show all children' : 'Hide all children';
}


function toggleChildren(span, event) {
    if (event.target.closest('.priority-over-drop-and-down')) {
        return;
    }

    const listItem = span.closest('.item').parentElement;
    const treeIcon = listItem.querySelector('.tree-icon');
    const childrenUl = listItem.querySelector('ul');

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

// ------------------------------ End drop down ------------------------------

// ------------------------------ Begin drag and drop ------------------------------
//no library used, only HTML Drag and Drop API

function drag(ev) {
    // stocke id de l'elem déplacé
    ev.dataTransfer.setData("text/plain", ev.target.children[0].id);
}

function drop(ev) {
    // empêche comportement par défaut
    ev.stopPropagation();
    ev.preventDefault();

    // recup id de l'elem déplacé
    var data = ev.dataTransfer.getData("text/plain");
    var draggedItemId = parseInt(data);

    // récupérer l'élément correspondant à l'ID
    var draggedItem = findMenuInList(draggedItemId, MENU_LIST);

    if (draggedItem) { // Vérifier si l'élément a été trouvé
        var targetItemId = parseInt(ev.target.closest(".item").id)

        var dropIndicator = document.querySelector('.drop-indicator')
        dropIndicator.style.display = 'none'

        var rect = ev.target.closest("div.item").getBoundingClientRect()
        var mouseY = ev.clientY - rect.top;
        var mouseX = ev.clientX - rect.left;

        const insertionBefore = mouseY < rect.height / 2
        const insertAsChild = mouseX > rect.width / 6

        // insère elem déplacé avant ou après elem cible en fonction de la position de dépôt
        const problems = insertMenuItem(draggedItemId, targetItemId, insertionBefore, insertAsChild)

        if (problems == 0){
            console.log("success")
        }
        else if (problems == 1){
            console.log("OSKOUR: element not found in list")
        }
        else if (problems == 2) {
            console.log("is parent")
        }
        else if (problems == 3) {
            console.log("same element")
        }

        generateMenu(MENU_LIST)

        generatePreviewMenus();
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
            while (draggedItem.childrens.length > 0){
                let popedChild = draggedItem.childrens.pop()
                if (root == 0){
                    MENU_LIST.splice(MENU_LIST.indexOf(draggedItem)+1, 0, popedChild)
                }
                else {
                    parentOfDragged.childrens.splice(parentOfDragged.childrens.indexOf(draggedItem)+1, 0, popedChild)
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

            if (newParent.childrens == null){
                newParent.childrens = [draggedItem]
            }
            else {
                newParent.childrens.push(draggedItem)
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
            insertionBefore ? parent.childrens.splice(parent.childrens.indexOf(findMenuInList(positionToInsert, MENU_LIST)), 0, menuToMove) : parent.childrens.splice(parent.childrens.indexOf(findMenuInList(positionToInsert, MENU_LIST))+1, 0, menuToMove)
            menuToMove.depth = parent.depth + 1
        }

        updateDepth(menuToMove, menuToMove.depth)
        return 0
    }
    return null
}

function isParentOf(parent, child){
    let parentElement = findMenuInList(parent, MENU_LIST)
    if (parentElement.childrens && parentElement.childrens.length > 0){
        for (const childElement of parentElement.childrens){
            if (childElement.id === child || isParentOf(childElement.id, child)) {
                return true
            }
        }
    }
    return false
}

function updateDepth(menuItem, depth){
    if (menuItem.childrens && menuItem.childrens.length > 0){
        for (const child of menuItem.childrens){
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
        if (menuItem.childrens && menuItem.childrens.length > 0){
            let childrens = menuItem.childrens
            for (const _ of childrens){
                let result = findParentOf(id, childrens)
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
        if (menuItem.childrens && menuItem.childrens.length > 0){
            let childrens = menuItem.childrens
            for (const child of childrens){
                let result = popFromMenuList(id, childrens)
                if (result) {
                    if (childrens.indexOf(result) !== -1){
                        return childrens.splice(childrens.indexOf(result), 1)[0]
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


// find the index of an element in MENU_LIST from its id
function findIndexOfMenuItem(itemId) {
    for (var i = 0; i < MENU_LIST.length; i++) {
        if (MENU_LIST[i].id === itemId) {
            return i;
        }
    }
    return -1;
}

function allowDrop(ev) {
    ev.preventDefault();

    // retrieve mouse position relative to the target element
    var rect = ev.target.closest("div.item").getBoundingClientRect();
    var mouseY = ev.clientY - rect.top;
    var mouseX = ev.clientX - rect.left;

    try{
        var targetItem = ev.target.closest(".item").parentElement;
        // display bar above or below the target element
        var dropIndicator = document.querySelector('.drop-indicator');

        dropIndicator.style.left = targetItem.offsetLeft + 'px'; // positions the bar to the left of the target element
        dropIndicator.style.width = targetItem.offsetWidth + 'px'; // adjust bar width to that of the target elem

        if (mouseY < rect.height / 2) { // if the mouse is over the target element
            dropIndicator.style.top = targetItem.offsetTop + 'px'; // position the bar above the target element
        } else { // if the mouse is below the target element => positions bar below
            dropIndicator.style.top = (targetItem.offsetTop + targetItem.offsetHeight) + 'px';
            if (mouseX > rect.width / 6) { // if the mouse is to the right of the target element
                dropIndicator.style.left = (targetItem.offsetLeft + targetItem.offsetWidth * 0.17) + 'px';
                dropIndicator.style.width = (targetItem.offsetWidth * 0.83) + 'px';
            }
        }
        dropIndicator.style.display = 'block';
    }
    catch{}
}

// ------------------------------ End drag and drop ------------------------------

// ------------------------------ Begin Preview ------------------------------
function generatePreviewMenus() {
    previewUl = document.getElementById('menus')
    previewUl.innerHTML = ""
    for (const menuItem of MENU_LIST){
        previewUl.innerHTML += generatePreviewMenuRecursive(menuItem, 1)
    }
}

function generatePreviewMenuRecursive(menuItem){
    let childrens = ""
    if (menuItem.childrens && menuItem.childrens.length > 0){
        for (const child of menuItem.childrens){
            childrens += generatePreviewMenuRecursive(child)
        }
    }
    let classes = (menuItem.depth >= 1) ? "parent deep" : "parent"
    let newMenu = `
    <li>
        <a>`+menuItem.title+`</a>
        <ul class="`+classes+`">
            `+childrens+`
        </ul>
    </li>`
    return newMenu;
}

// ------------------------------ End Preview ------------------------------

function saveData() {
    allowUnload = true
    const menuListJSON = JSON.stringify(MENU_LIST);
    document.getElementById('menuData').value = menuListJSON;
    document.getElementById('savedData').submit();
}

function addMenu() {
    document.getElementById('addMenuForm').submit();
}

function deleteMenu() {
    document.getElementById('deleteForm').submit();
}

document.addEventListener('DOMContentLoaded', function() {
    // Function to remove flash messages from the DOM
    function removeFlashMessages() {
        const flashMessages = document.querySelectorAll('.alert');
        flashMessages.forEach(function(message) {
            message.remove();
        });
    }

    // Function to notify server to clear flash messages
    function clearFlashMessagesOnServer() {
        fetch('/admin/module/CustomFrontMenu/clearFlashes')
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok ' + response.statusText);
                }
                return response.json();
            })
            .then(data => {
                console.log('Server response:', data);
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }

    // Add a click event listener to the document
    document.addEventListener('click', function(event) {
        if (document.getElementById('alert-flash')) {
            removeFlashMessages();
            clearFlashMessagesOnServer();
        }

    });
});

window.onload = function() {
    MENU_NAMES = getMenuNames()
    MENU_LIST = getMenuList()
    generateSelect(MENU_NAMES)
    generateMenu(MENU_LIST)
    generatePreviewMenus()
}

let allowUnload = false;

window.addEventListener('beforeunload', function(event) {
    if (!allowUnload) {
        //event.preventDefault();
    }
}, { capture: true });


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