<template lang="html">
  <div>
    <div class="container">
      <div class="row">
        <h4>Handle panels</h4>
      </div>
    </div>

    <div class="database">
      <table id="strain-list" class="table table-condensed database">
        <thead><tr>
          <th style="min-width: 200px;">Name</th>
          <th v-for="mlva in mlvadata" class="rotate"><div>{{ mlva }}</div></th>
          <th style="min-width: 320px;"></th>
        </tr></thead>
        <tbody>
          <tr v-for="panel in panels">
            <td><input class="form-control" v-model="panel.name" placeholder="Panel Name" type="text" /></td>
            <td v-for="mlva in mlvadata" class="marker-checkbox"><input type="checkbox" v-model="panel.data[mlva]"/></td>
            <td>
              <button class="btn btn-default" @click.prevent="updatePanel(panel)">Update</button>
              <button class="btn btn-default" @click.prevent="generateGN(panel, $event)" data-loading-text="<i class='fa fa-circle-o-notch fa-spin'></i> Generating">Generate Geno Num</button>
              <button class="btn btn-default" @click.prevent="deletePanel(panel)">Delete</button>
            </td>
          </tr>
          <tr>
            <td><input class="form-control" v-model="nPanel.name" placeholder="New Panel"/></td>
            <td v-for="mlva in mlvadata" class="marker-checkbox"><input type="checkbox" v-model="nPanel.data[mlva]"/></td>
            <td>
              <button @click.prevent="invertSelection" class="btn btn-default">Invert selection</button>
              <button @click.prevent="createPanel" class="btn btn-default">Submit</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script>
import Request from '../../lib/request'
import { generateTempGN } from '../../lib/genonums'

let baseId, allBasedata

function convertPanel (panel) { return Object.assign({}, panel, { data: filterA2O(panel.data) }) }
function filterO2A (obj) { return allBasedata.filter(bd => obj[bd]) }
function filterA2O (arr) {
  let data = {}
  for (let bd of allBasedata) data[bd] = arr.includes(bd)
  return data
}
function newPanel (arr) {
  let data= {}
  for (let bd of allBasedata) data[bd] = false
  return data
}


function emptyPanel () { return { name: '', data: newPanel(allBasedata) } }

export default {
  data () {
    baseId = this.$store.state.base.id
    allBasedata = this.$store.getters.allMlvadata
    return { panels: this.$store.state.panels.list.map(convertPanel), nPanel: emptyPanel() }
  },
  computed: {
    mlvadata () { return this.$store.getters.allMlvadata }
  },
  methods: {
    createPanel () {
      let data = filterO2A(this.nPanel.data)
      let name = this.nPanel.name
      if (name.trim() === '' || data.length === 0) return
      Request.post('panels/make', { baseId, name, data })
        .then(p => {
          let panel = convertPanel(p)
          this.panels.push(panel)
          this.$store.commit('addPanel', p)
          this.nPanel = emptyPanel()
          window.location.reload()
        })
    },
    updatePanel (panel) {
      let data = filterO2A(panel.data)
      console.log(data)
      let name = panel.name
      console.log(name)
      if (name.trim() === '' || data.length === 0) return
      Request.post('panels/update/' + panel.id, { name, data })
        .then(p => this.$store.commit('updatePanel', p))
    },
    deletePanel (panel) {
      Request.post('panels/delete/' + panel.id)
        .then(() => {
          this.panels = this.panels.filter(p => p !== panel)
          this.$store.commit('deletePanel', panel)
        })
    },
    generateGN (panel, e) {
      /* global $ */
      $(e.target).button('loading')
      let nData = generateTempGN(panel.id)
      console.log(nData)
      if (nData.length === 0) return
      Request.postBlob('panels/addGN/' + panel.id, nData)
        .then(gnList => {
          for (let gn of gnList) this.$store.commit('addGN', { panelId: panel.id, gn })
          $(e.target).button('reset')
          window.location.reload()
        })
    },
    invertSelection () {
      Array.prototype.forEach.call(allBasedata, mlva => {
        this.nPanel.data[mlva] = ! this.nPanel.data[mlva]
      })
    }
  }
}
</script>

<style lang="scss">
.marker-checkbox {
  padding: 10px !important;
  text-align: center;
}

.table > thead > tr > th.rotate {
  cursor: pointer;
  white-space: nowrap;
  vertical-align: unset;
  text-align: -webkit-center;

  border-left: 1px solid #ccc;

  & > div {
    // writing-mode: vertical-rl;
    // text-orientation: upright;
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    letter-spacing: 0.2em;
  }
}
</style>
