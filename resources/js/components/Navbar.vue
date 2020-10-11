<template>
  <nav class="navbar">
    <RouterLink class="navbar__brand" to="/">Photogram</RouterLink>
    <div class="navbar__menu">
      <div v-if="isLogin" class="navbar__item">
        <button class="button" @click="showForm = !showForm">
          <i class="icon ion-md-add"></i>
          Submit a photo
        </button>
      </div>
      <div v-if="isLogin" class="navbar__item">
        <RouterLink  class="button button--link" to="/userPhoto"><button class="button">自分の投稿</button></RouterLink>
      </div>
      <div v-if="isLogin" class="navbar__item">
        <RouterLink  class="button button--link" to="/userLike"><button class="button">いいねした写真</button></RouterLink>
      </div>
      <span v-if="isLogin" class="navbar__item">{{username}}</span>
      <div v-else class="navbar__item">
        <RouterLink class="button button--link" to="/login">Login / Register</RouterLink>
      </div>
    </div>
    <!-- v-modelは v-bindと@inputから出来ている -->
    <PhotoForm v-model="showForm" />
  </nav>
</template>

<script>
import PhotoForm from './PhotoForm.vue'

export default {
  components: {
    PhotoForm
  },
  data() {
    return {
      showForm: false
    }
  },
  computed: {
    isLogin() {
      return this.$store.getters['auth/check']
    },
    username() {
      return this.$store.getters['auth/username']
    }
  }
}
</script>