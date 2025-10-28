import{B as h,aC as v,a0 as $,R as w,H as P}from"./useLexicon.min.js";import{l as k,a as g,b as d,d as a,e as O,j as c,g as p,n as u,m as l,t as f,v as j,i as B,k as S,w as D,C,T as L}from"./_plugin-vue_export-helper.min.js";var I=({dt:e})=>`
.p-fieldset {
    background: ${e("fieldset.background")};
    border: 1px solid ${e("fieldset.border.color")};
    border-radius: ${e("fieldset.border.radius")};
    color: ${e("fieldset.color")};
    padding: ${e("fieldset.padding")};
    margin: 0;
}

.p-fieldset-legend {
    background: ${e("fieldset.legend.background")};
    border-radius: ${e("fieldset.legend.border.radius")};
    border-width: ${e("fieldset.legend.border.width")};
    border-style: solid;
    border-color: ${e("fieldset.legend.border.color")};
    padding: ${e("fieldset.legend.padding")};
    transition: background ${e("fieldset.transition.duration")}, color ${e("fieldset.transition.duration")}, outline-color ${e("fieldset.transition.duration")}, box-shadow ${e("fieldset.transition.duration")};
}

.p-fieldset-toggleable > .p-fieldset-legend {
    padding: 0;
}

.p-fieldset-toggle-button {
    cursor: pointer;
    user-select: none;
    overflow: hidden;
    position: relative;
    text-decoration: none;
    display: flex;
    gap: ${e("fieldset.legend.gap")};
    align-items: center;
    justify-content: center;
    padding: ${e("fieldset.legend.padding")};
    background: transparent;
    border: 0 none;
    border-radius: ${e("fieldset.legend.border.radius")};
    transition: background ${e("fieldset.transition.duration")}, color ${e("fieldset.transition.duration")}, outline-color ${e("fieldset.transition.duration")}, box-shadow ${e("fieldset.transition.duration")};
    outline-color: transparent;
}

.p-fieldset-legend-label {
    font-weight: ${e("fieldset.legend.font.weight")};
}

.p-fieldset-toggle-button:focus-visible {
    box-shadow: ${e("fieldset.legend.focus.ring.shadow")};
    outline: ${e("fieldset.legend.focus.ring.width")} ${e("fieldset.legend.focus.ring.style")} ${e("fieldset.legend.focus.ring.color")};
    outline-offset: ${e("fieldset.legend.focus.ring.offset")};
}

.p-fieldset-toggleable > .p-fieldset-legend:hover {
    color: ${e("fieldset.legend.hover.color")};
    background: ${e("fieldset.legend.hover.background")};
}

.p-fieldset-toggle-icon {
    color: ${e("fieldset.toggle.icon.color")};
    transition: color ${e("fieldset.transition.duration")};
}

.p-fieldset-toggleable > .p-fieldset-legend:hover .p-fieldset-toggle-icon {
    color: ${e("fieldset.toggle.icon.hover.color")};
}

.p-fieldset .p-fieldset-content {
    padding: ${e("fieldset.content.padding")};
}
`,E={root:function(n){var t=n.props;return["p-fieldset p-component",{"p-fieldset-toggleable":t.toggleable}]},legend:"p-fieldset-legend",legendLabel:"p-fieldset-legend-label",toggleButton:"p-fieldset-toggle-button",toggleIcon:"p-fieldset-toggle-icon",contentContainer:"p-fieldset-content-container",content:"p-fieldset-content"},K=h.extend({name:"fieldset",style:I,classes:E}),N={name:"BaseFieldset",extends:P,props:{legend:String,toggleable:Boolean,collapsed:Boolean,toggleButtonProps:{type:null,default:null}},style:K,provide:function(){return{$pcFieldset:this,$parentInstance:this}}},A={name:"Fieldset",extends:N,inheritAttrs:!1,emits:["update:collapsed","toggle"],data:function(){return{d_collapsed:this.collapsed}},watch:{collapsed:function(n){this.d_collapsed=n}},methods:{toggle:function(n){this.d_collapsed=!this.d_collapsed,this.$emit("update:collapsed",this.d_collapsed),this.$emit("toggle",{originalEvent:n,value:this.d_collapsed})},onKeyDown:function(n){(n.code==="Enter"||n.code==="NumpadEnter"||n.code==="Space")&&(this.toggle(n),n.preventDefault())}},computed:{buttonAriaLabel:function(){return this.toggleButtonProps&&this.toggleButtonProps.ariaLabel?this.toggleButtonProps.ariaLabel:this.legend}},directives:{ripple:w},components:{PlusIcon:$,MinusIcon:v}};function s(e){"@babel/helpers - typeof";return s=typeof Symbol=="function"&&typeof Symbol.iterator=="symbol"?function(n){return typeof n}:function(n){return n&&typeof Symbol=="function"&&n.constructor===Symbol&&n!==Symbol.prototype?"symbol":typeof n},s(e)}function b(e,n){var t=Object.keys(e);if(Object.getOwnPropertySymbols){var o=Object.getOwnPropertySymbols(e);n&&(o=o.filter(function(i){return Object.getOwnPropertyDescriptor(e,i).enumerable})),t.push.apply(t,o)}return t}function m(e){for(var n=1;n<arguments.length;n++){var t=arguments[n]!=null?arguments[n]:{};n%2?b(Object(t),!0).forEach(function(o){F(e,o,t[o])}):Object.getOwnPropertyDescriptors?Object.defineProperties(e,Object.getOwnPropertyDescriptors(t)):b(Object(t)).forEach(function(o){Object.defineProperty(e,o,Object.getOwnPropertyDescriptor(t,o))})}return e}function F(e,n,t){return(n=T(n))in e?Object.defineProperty(e,n,{value:t,enumerable:!0,configurable:!0,writable:!0}):e[n]=t,e}function T(e){var n=V(e,"string");return s(n)=="symbol"?n:n+""}function V(e,n){if(s(e)!="object"||!e)return e;var t=e[Symbol.toPrimitive];if(t!==void 0){var o=t.call(e,n);if(s(o)!="object")return o;throw new TypeError("@@toPrimitive must return a primitive value.")}return(n==="string"?String:Number)(e)}var M=["id"],R=["id","aria-controls","aria-expanded","aria-label"],z=["id","aria-labelledby"];function H(e,n,t,o,i,r){var y=k("ripple");return d(),g("fieldset",l({class:e.cx("root")},e.ptmi("root")),[a("legend",l({class:e.cx("legend")},e.ptm("legend")),[c(e.$slots,"legend",{toggleCallback:r.toggle},function(){return[e.toggleable?p("",!0):(d(),g("span",l({key:0,id:e.$id+"_header",class:e.cx("legendLabel")},e.ptm("legendLabel")),f(e.legend),17,M)),e.toggleable?u((d(),g("button",l({key:1,id:e.$id+"_header",type:"button","aria-controls":e.$id+"_content","aria-expanded":!i.d_collapsed,"aria-label":r.buttonAriaLabel,class:e.cx("toggleButton"),onClick:n[0]||(n[0]=function(){return r.toggle&&r.toggle.apply(r,arguments)}),onKeydown:n[1]||(n[1]=function(){return r.onKeyDown&&r.onKeyDown.apply(r,arguments)})},m(m({},e.toggleButtonProps),e.ptm("toggleButton"))),[c(e.$slots,e.$slots.toggleicon?"toggleicon":"togglericon",{collapsed:i.d_collapsed,class:j(e.cx("toggleIcon"))},function(){return[(d(),B(S(i.d_collapsed?"PlusIcon":"MinusIcon"),l({class:e.cx("toggleIcon")},e.ptm("toggleIcon")),null,16,["class"]))]}),a("span",l({class:e.cx("legendLabel")},e.ptm("legendLabel")),f(e.legend),17)],16,R)),[[y]]):p("",!0)]})],16),O(L,l({name:"p-toggleable-content"},e.ptm("transition")),{default:D(function(){return[u(a("div",l({id:e.$id+"_content",class:e.cx("contentContainer"),role:"region","aria-labelledby":e.$id+"_header"},e.ptm("contentContainer")),[a("div",l({class:e.cx("content")},e.ptm("content")),[c(e.$slots,"default")],16)],16,z),[[C,!i.d_collapsed]])]}),_:3},16)],16)}A.render=H;export{A as s};
