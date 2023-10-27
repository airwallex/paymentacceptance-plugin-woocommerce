const methodsConfig = require('../methodsConfig.json');

const normalizedName = (name) => {
    name = name.replace('", "airwallex-online-payments-gateway")', '');
    return name.replace('__("', '');
};
const getMethodNames = () => {
    return Object.values(methodsConfig).map((method) =>
        normalizedName(method.defaultTitle),
    );
};
const allMethodsIds = Object.keys(methodsConfig);
const allMethods = methodsConfig;
module.exports = {
    normalizedName,
    getMethodNames,
    allMethods,
    allMethodsIds,
};
