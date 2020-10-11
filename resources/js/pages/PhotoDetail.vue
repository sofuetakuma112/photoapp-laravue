<template>
  <div
    v-if="photo"
    class="photo-detail"
    :class="{ 'photo-detail--column': fullWidth }"
  >
    <figure
      class="photo-detail__pane photo-detail__image"
      @click="fullWidth = !fullWidth"
    >
      <img :src="photo.url" alt="" />
      <figcaption>Posted by {{ photo.owner.name }}</figcaption>
      <button
        class="button button--follow"
        :class="{ 'button--liked': alreadyFollowUser }"
        title="Follow user"
        @click.stop="onFollowClick"
      >
        <i class="icon ion-md-heart"></i>フォロー
      </button>
      <button class="button" title="Delete photo" @click.stop="deletePhoto">
        削除
      </button>
    </figure>
    <div class="photo-detail__pane">
      <button
        class="button button--like"
        :class="{ 'button--liked': photo.liked_by_user }"
        title="Like photo"
        @click="onLikeClick"
      >
        <i class="icon ion-md-heart"></i>{{ photo.likes_count }}
      </button>
      <a
        :href="`/photos/${photo.id}/download`"
        class="button"
        title="Download photo"
      >
        <i class="icon ion-md-arrow-round-down"></i>Download
      </a>
      <div v-if="photo.tags.length" class="tag_wrapper">
        <ul class="tag_list">
          <span class="tag_title">タグ:</span>
          <li v-for="tag in photo.tags" :key="tag.name" class="tag_item">
            {{ tag.name }}
          </li>
        </ul>
      </div>
      <h2 class="photo-detail__title">
        <i class="icon ion-md-chatboxes"></i>Comments
      </h2>
      <ul v-if="photo.comments.length > 0" class="photo-detail__comments">
        <li
          v-for="comment in photo.comments"
          :key="comment.content"
          class="photo-detail__commentItem"
        >
          <p class="photo-detail__commentBody">
            {{ comment.content }}
          </p>
          <p class="photo-detail__commentInfo">
            {{ comment.author.name }}
          </p>
        </li>
      </ul>
      <p v-else>No comments yet.</p>
      <form @submit.prevent="addComment" class="form" v-if="isLogin">
        <div class="errors" v-if="commentErrors">
          <ul v-if="commentErrors.content">
            <li v-for="msg in commentErrors.content" :key="msg">{{ msg }}</li>
          </ul>
        </div>
        <textarea class="form__item" v-model="commentContent"></textarea>
        <div class="form__button">
          <button class="button button--inverse" type="submit">
            submit comment
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script>
import { OK, CREATED, UNPROCESSABLE_ENTITY } from "../util";

export default {
  props: {
    id: {
      type: String,
      required: true,
    },
  },
  data() {
    return {
      photo: null,
      fullWidth: false,
      commentContent: "",
      commentErrors: null,
      alreadyFollowUser: null,
    };
  },
  computed: {
    isLogin() {
      return this.$store.getters["auth/check"];
    },
  },
  methods: {
    async fetchPhoto() {
      const response = await axios.get(`/api/photos/${this.id}`);

      if (response.status !== OK) {
        this.$store.commit("error/setCode", response.status);
        return false;
      }

      this.photo = response.data;
      this.checkUserFollow();
    },
    async checkUserFollow() {
      const response = await axios.get(
        `/api/user/${this.photo.user_id}/checkFollow`
      ); // JSONを返す

      if (response.status !== OK) {
        this.$store.commit("error/setCode", response.status);
        return false;
      }

      if (response.data) {
        this.alreadyFollowUser = true;
      } else {
        this.alreadyFollowUser = false;
      }

      console.log(response.data);
    },
    async addComment() {
      // 返り値に投稿したコメントのデータが返ってくる
      const response = await axios.post(`/api/photos/${this.id}/comments`, {
        content: this.commentContent,
      });

      if (response.status === UNPROCESSABLE_ENTITY) {
        this.commentErrors = response.data.errors;
        return false;
      }

      this.commentContent = "";
      this.commentErrors = null;

      if (response.status !== CREATED) {
        this.$store.commit("error/setCode", response.status);
        return false;
      }

      this.photo.comments = [response.data, ...this.photo.comments];
    },
    onLikeClick() {
      if (!this.isLogin) {
        alert("いいね機能を使うにはログインしてください。");
        return false;
      }

      if (this.photo.liked_by_user) {
        this.unlike();
      } else {
        this.like();
      }
    },
    async like() {
      const response = await axios.put(`/api/photos/${this.id}/like`);

      if (response.status !== OK) {
        this.$store.commit("error/setCode", response.status);
        return false;
      }

      this.photo.likes_count = this.photo.likes_count + 1;
      this.photo.liked_by_user = true;
    },
    async unlike() {
      const response = await axios.delete(`/api/photos/${this.id}/like`);

      if (response.status !== OK) {
        this.$store.commit("error/setCode", response.status);
        return false;
      }

      this.photo.likes_count = this.photo.likes_count - 1;
      this.photo.liked_by_user = false;
    },
    onFollowClick() {
      if (!this.isLogin) {
        alert("フォローするにはログインしてください。");
        return false;
      }

      if (this.alreadyFollowUser) {
        this.unfollow();
      } else {
        this.follow();
      }
    },
    async follow() {
      const response = await axios.put(
        `/api/photos/${this.photo.user_id}/follow`
      );

      if (response.status !== OK) {
        this.$store.commit("error/setCode", response.status);
        return false;
      }

      this.alreadyFollowUser = true;
    },
    async unfollow() {
      const response = await axios.delete(
        `/api/photos/${this.photo.user_id}/unfollow`
      );

      if (response.status !== OK) {
        this.$store.commit("error/setCode", response.status);
        return false;
      }

      this.alreadyFollowUser = false;
    },
    async deletePhoto() {
      if (confirm('Delete this photo?')) {
        const response = await axios.delete(`/api/photos/${this.photo.id}/delete`)

      if (response.status !== OK) {
        this.$store.commit("error/setCode", response.status);
        return false;
      }

      this.$router.push(`/`);
      }
    }
  },
  watch: {
    $route: {
      async handler() {
        await this.fetchPhoto();
      },
      immediate: true,
    },
  },
};
</script>

<style scoped>
.tag_wrapper {
  margin-top: 0.5rem;
}
.tag_list {
  margin: 0;
  padding: 0;
  display: flex;
}
.tag_title {
  margin-right: 0.25rem;
}
.tag_item {
  list-style: none;
  padding: 0 0.25rem;
}
</style>