build.task("publish")
    .spawn2("node", path.join(process.env.GOPATH, "../node/tools/rpublish"));
