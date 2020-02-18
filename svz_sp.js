function addNeutralListener(element){
	return element.addEventListener 
	? function(type, callback, options) { return element.addEventListener(type, callback, options)}
	: function(type, callback, options) {
		return element.attachEvent( `on${type}`, callback, options);
	}
}

addNeutralListener(window)('load',() =>{
	const images = Array.from(document.getElementsByClassName('svz-sp-image'));
	const resize = () => {
		for (const i in images){
		    images[i].parentNode.style.height = `${images[i].parentNode.clientWidth}px`
		}
	}
	resize();
	let waiting=false;
	addNeutralListener(window)('resize', () => {
		if(!waiting){
			waiting = setTimeout(() => waiting = false, 20);
			resize();
		}
	})
})