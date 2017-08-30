var template = `
  <div ref="container" class="column is-one-quarter">
    <img v-lazy="image.id"/>
    <div v-lazy:background-image="image.id"></div>
    </div>
`
Vue.component('image-item', { props: ['image'], template: template })

Vue.use(VueLazyload)  
        
new Vue({
        
  el: '#app',
        
  data: {
    images : []
  },
        
  created: function () {
    this.fetchImages()
  },
        
  methods: {
    fetchImages: function () {
      var vm = this
      var region = 'full'
      var rotation = '0'
      var size = ',250'
      axios.get('http://dev-sites.dlib.nyu.edu/imageurlprocessor/examples/alba/albamoscow.json')
        .then(function (response) {
          response.data.map(function (image) {
            var identifier = encodeURIComponent(encodeURIComponent(image.id))
            image.id = 'http://dev-sites.dlib.nyu.edu/imageurlprocessor/books/' + identifier + '/' + region + '/' + size + '/' + rotation + '/default.jpg'
            vm.images.push(image)
          })
        })
        .catch((error) => {
          console.log('Error! Could not reach the API. ' + error)
        })
    }
  }
})
