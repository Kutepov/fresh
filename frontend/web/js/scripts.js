if(location.hash === '#contact'){
    document.querySelector('#contact-form').classList.add('is-opened');
}

var popupOpen = document.querySelectorAll('.popup-open');
for(var i = 0; i < popupOpen.length; i++){
    popupOpen[i].addEventListener('click',function(e){
        var active = document.querySelector('.popup.is-opened');
        if(typeof(active) != 'undefined' && active != null){
            active.classList.remove('is-opened');
        }
        document.querySelector(this.getAttribute('data-popup')).classList.add('is-opened');
    },false);
}

var popupClose = document.querySelectorAll('.popup-close');
for(var i = 0; i < popupClose.length; i++){
    popupClose[i].addEventListener('click',function(e){
        this.parentNode.parentNode.classList.remove('is-opened');
    },false);
}
//# sourceMappingURL=data:application/json;charset=utf8;base64,eyJ2ZXJzaW9uIjozLCJzb3VyY2VzIjpbInNjcmlwdHMuanMiXSwibmFtZXMiOltdLCJtYXBwaW5ncyI6IkFBQUE7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBO0FBQ0E7QUFDQTtBQUNBIiwiZmlsZSI6InNjcmlwdHMuanMiLCJzb3VyY2VzQ29udGVudCI6WyJpZihsb2NhdGlvbi5oYXNoID09PSAnI2NvbnRhY3QnKXtcclxuICAgIGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3IoJyNjb250YWN0LWZvcm0nKS5jbGFzc0xpc3QuYWRkKCdpcy1vcGVuZWQnKTtcclxufVxyXG5cclxudmFyIHBvcHVwT3BlbiA9IGRvY3VtZW50LnF1ZXJ5U2VsZWN0b3JBbGwoJy5wb3B1cC1vcGVuJyk7XHJcbmZvcih2YXIgaSA9IDA7IGkgPCBwb3B1cE9wZW4ubGVuZ3RoOyBpKyspe1xyXG4gICAgcG9wdXBPcGVuW2ldLmFkZEV2ZW50TGlzdGVuZXIoJ2NsaWNrJyxmdW5jdGlvbihlKXtcclxuICAgICAgICB2YXIgYWN0aXZlID0gZG9jdW1lbnQucXVlcnlTZWxlY3RvcignLnBvcHVwLmlzLW9wZW5lZCcpO1xyXG4gICAgICAgIGlmKHR5cGVvZihhY3RpdmUpICE9ICd1bmRlZmluZWQnICYmIGFjdGl2ZSAhPSBudWxsKXtcclxuICAgICAgICAgICAgYWN0aXZlLmNsYXNzTGlzdC5yZW1vdmUoJ2lzLW9wZW5lZCcpO1xyXG4gICAgICAgIH1cclxuICAgICAgICBkb2N1bWVudC5xdWVyeVNlbGVjdG9yKHRoaXMuZ2V0QXR0cmlidXRlKCdkYXRhLXBvcHVwJykpLmNsYXNzTGlzdC5hZGQoJ2lzLW9wZW5lZCcpO1xyXG4gICAgfSxmYWxzZSk7XHJcbn1cclxuXHJcbnZhciBwb3B1cENsb3NlID0gZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbCgnLnBvcHVwLWNsb3NlJyk7XHJcbmZvcih2YXIgaSA9IDA7IGkgPCBwb3B1cENsb3NlLmxlbmd0aDsgaSsrKXtcclxuICAgIHBvcHVwQ2xvc2VbaV0uYWRkRXZlbnRMaXN0ZW5lcignY2xpY2snLGZ1bmN0aW9uKGUpe1xyXG4gICAgICAgIHRoaXMucGFyZW50Tm9kZS5wYXJlbnROb2RlLmNsYXNzTGlzdC5yZW1vdmUoJ2lzLW9wZW5lZCcpO1xyXG4gICAgfSxmYWxzZSk7XHJcbn0iXX0=
