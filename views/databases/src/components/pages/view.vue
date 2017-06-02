<template lang="html">
  <div>
    <div class="container">
      <div class="row"><p>
        Columns (click to hide) :
        <a role="button" :class="$store.getters.isKeyVisible ? '' : 'text-muted'" @click="toggle('key')">Key</a>
        <template  v-for="meta in allMetadata">
          - <a role="button" :class="metadata.includes(meta) ? '' : 'text-muted'" @click="toggle(meta)">{{ meta }}</a>
        </template>
      </p></div>
    </div>

    <pagination :page="page" :total="nbStrains"></pagination>

    <div class="database">
      <table class="table table-condensed table-striped">
        <thead>
          <tr>
            <th @click="setSortBy('[dist]')" v-if="queried">Distance to reference <span :class="orderIcon('[dist]')" aria-hidden="true"></span></th>
            <th @click="setSortBy('[key]')" v-if="$store.getters.isKeyVisible">Key <span :class="orderIcon('[key]')" aria-hidden="true"></span></th>
            <th v-for="meta in metadata" @click="setSortBy(meta)">{{ meta }} <span :class="orderIcon(meta)" aria-hidden="true"></span></th>
            <th @click="setSortBy('[gn]')" v-if="currentPanel">GN <span :class="orderIcon('[gn]')" aria-hidden="true"></span></th>
            <th v-for="mlva in mlvadata" @click="setSortBy(mlva)" class="rotate">
              <div><span>{{ mlva }} <span :class="orderIcon(mlva)" aria-hidden="true"></span></span></div>
            </th>
          </tr>
        </thead>

        <tbody>
          <tr v-if="queried">
            <td :colspan="1 + ($store.getters.isKeyVisible ? 1 : 0) + metadata.length"><b>Reference strain</b></td>
            <td class="marker" v-for="mlva in mlvadata">{{ $store.state.strains.query.ref[mlva] }}</td>
          </tr>
          <tr v-for="strain in strains">
            <td class="colkey" v-if="$store.getters.isKeyVisible">{{ strain.name }}</td>
            <td v-for="meta in metadata" v-html="autolink(strain.metadata[meta])"></td>
            <td v-if="currentPanel">{{ getGN(strain) }}</td>
            <td v-if="queried">{{ strain.deltaDist }}</td>
            <td v-for="mlva in mlvadata" class="marker" :style="colorMaker(mlva, strain.data[mlva])">{{ strain.data[mlva] }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <pagination :page="page" :total="nbStrains"></pagination>

  </div>
</template>

<script>
import { getGN } from '../../lib/genonums'
import { autolink, LightenDarkenColor } from '../../lib/utils'
import pagination from '../partials/pagination.vue'

export default {
  data () {
    return {
      page: { current: 1, perPage: 20 },
      order: 'asc',
      sortBy: '[key]'
    }
  },
  components: { pagination },
  computed: {
    currentPanel () { return this.$store.getters.currentPanel },
    queried () { return this.$store.getters.queried },
    allStrains () { return this.$store.getters.strains },
    nbStrains () { return this.allStrains.length },
    allMetadata () { return this.$store.getters.allMetadata },
    metadata () { return this.$store.getters.metadata },
    mlvadata () { return this.$store.getters.mlvadata },
    strains () {
      return this.allStrains
        .sort((a, b) => {
          if (this.sortBy === '[key]') {
            a = a.name; b = b.name
          } else if (this.sortBy === '[dist]') {
            a = a.deltaDist; b = b.deltaDist
          } else if (this.sortBy === '[gn]') {
            a = this.getGN(a); b = this.getGN(b)
          } else {
            let header = this.$store.state.headers.list.find(h => h.name === this.sortBy)
            let type = header.type === 'mlva' ? 'data' : 'metadata'
            a = a[type][header.name]; b = b[type][header.name]
          }
          return (a === b ? 0 : a > b ? 1 : -1) * this.order
        })
        .slice(this.page.perPage * (this.page.current - 1), this.page.perPage * this.page.current)
    }
  },
  methods: {
    autolink,
    toggle (name) { this.$store.commit('toggleHeader', name) },
    setSortBy (value) {
      this.page.current = 1
      if (this.sortBy === value) {
        this.order *= -1
      } else {
        this.sortBy = value
        this.order = 1
      }
    },
    colorMaker (mlva, value) {
      const max = 10
      let fgColor, bgColor
      if (!isNaN(parseInt(value))) {
        let x = Math.min(parseInt(value), max) / max
        let r = parseInt(x * 240 + (1 - x) * 30)
        let g = parseInt(x * 30 + (1 - x) * 240)
        let b = parseInt(x * 0 + (1 - x) * 0)
        fgColor = ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)
        bgColor = LightenDarkenColor(fgColor, 50)
      } else {
        fgColor = '333'
        bgColor = 'fff'
      }
      let weigth = 'normal'
      if (this.queried) {
        weigth = this.$store.state.strains.query.ref[mlva] === value ? 'bold' : 'normal'
      }
      return {
        color: '#' + fgColor,
        background: '#' + bgColor,
        fontWeight: weigth
      }
    },
    orderIcon (name) {
      if (this.sortBy !== name) {
        return 'glyphicon glyphicon-triangle-right muted'
      } else {
        return 'glyphicon glyphicon-triangle-' + (this.order > 0 ? 'top' : 'bottom')
      }
    },
    getGN (strain) {
      if (!this.currentPanel) return ''
      let gn = getGN(this.currentPanel, strain)
      return gn ? gn.value : ''
    }
  }
}
</script>

<style lang="scss">
.database { overflow-x: auto; }

th .glyphicon { font-size: 10px; }
.marker { width: 40px; max-width: 40px; }

.table > thead > tr > th.rotate {
  height: 60px;
  white-space: nowrap;
  vertical-align: inherit;

  & > div {
    transform:
    translate(20px, 10px)
    rotate(315deg);
    width: 30px;
    & > span {
      border-bottom: 1px solid #ccc;
      padding: 5px 10px;
      padding-left: 0px;
    }
  }
}

.muted { opacity: 0.5; }
</style>